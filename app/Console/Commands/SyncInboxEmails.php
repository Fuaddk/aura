<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\InboxEmail;
use App\Models\Task;
use Illuminate\Console\Command;

class SyncInboxEmails extends Command
{
    protected $signature   = 'inbox:sync {--account= : Sync a specific account ID}';
    protected $description = 'Sync all active email accounts and analyse new emails';

    public function handle(): void
    {
        $query = EmailAccount::where('is_active', true)->where('auto_sync', true);

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('No active email accounts to sync.');
            return;
        }

        foreach ($accounts as $account) {
            $this->syncAccount($account);
        }
    }

    private function syncAccount(EmailAccount $account): void
    {
        $this->info("Syncing {$account->email}...");

        $sock = $this->imapConnect($account->imap_host, $account->imap_port);
        if (!is_resource($sock)) {
            $this->error("  Connect failed: {$sock}");
            return;
        }

        $login = $this->imapLogin($sock, $account->email, $account->imap_password);
        if ($login !== true) {
            fclose($sock);
            $this->error("  Login failed: {$login}");
            return;
        }

        $this->socketCmd($sock, 'A3 SELECT INBOX');

        $since = date('d-M-Y', strtotime('-30 days'));
        $resp  = $this->socketCmd($sock, "A4 SEARCH SINCE {$since}");

        $uids = [];
        foreach (explode("\n", $resp) as $line) {
            if (str_starts_with(trim($line), '* SEARCH')) {
                $parts = explode(' ', trim($line));
                array_shift($parts);
                array_shift($parts);
                $uids = array_values(array_filter($parts, 'is_numeric'));
                $uids = array_map('intval', $uids);
                break;
            }
        }

        if (empty($uids)) {
            fclose($sock);
            $account->update(['last_synced_at' => now()]);
            $this->line('  No emails found.');
            return;
        }

        rsort($uids);
        $uids = array_slice($uids, 0, 30);

        $newEmails  = 0;
        $totalTasks = 0;

        foreach ($uids as $uid) {
            $uidStr = (string) $uid;

            if (InboxEmail::where('email_account_id', $account->id)->where('message_uid', $uidStr)->exists()) {
                continue;
            }

            $headerRaw  = $this->socketCmd($sock, "A5 FETCH {$uid} (BODY[HEADER.FIELDS (FROM SUBJECT DATE)])");
            $subject    = $this->parseHeader($headerRaw, 'Subject') ?: '(Ingen emne)';
            $fromRaw    = $this->parseHeader($headerRaw, 'From') ?: '';
            $dateRaw    = $this->parseHeader($headerRaw, 'Date') ?: '';

            [$fromEmail, $fromName] = $this->parseFrom($fromRaw);
            $receivedAt = $dateRaw ? date('Y-m-d H:i:s', strtotime($dateRaw)) : now()->toDateTimeString();

            $bodyRaw = $this->socketCmd($sock, "A6 FETCH {$uid} (BODY[1])");
            $body    = $this->extractFetchBody($bodyRaw);
            $snippet = mb_substr(strip_tags($body), 0, 500);

            $analysis     = $this->analyseEmail($subject, $snippet);
            $isRelevant   = !empty($analysis['relevant']);
            $tasksCreated = 0;

            if ($isRelevant && !empty($analysis['tasks'])) {
                $tasksCreated = $this->createTasks($account, $subject, $fromName, $analysis['tasks']);
            }

            InboxEmail::create([
                'email_account_id' => $account->id,
                'user_id'          => $account->user_id,
                'message_uid'      => $uidStr,
                'subject'          => $subject,
                'from_email'       => $fromEmail,
                'from_name'        => $fromName,
                'received_at'      => $receivedAt,
                'snippet'          => $snippet,
                'is_relevant'      => $isRelevant,
                'analysis_result'  => $analysis,
                'tasks_created'    => $tasksCreated,
            ]);

            $newEmails++;
            $totalTasks += $tasksCreated;

            $this->line("  [{$uid}] {$subject}" . ($isRelevant ? " → {$tasksCreated} opgave(r)" : ''));
        }

        $this->socketCmd($sock, 'A7 LOGOUT');
        fclose($sock);

        $account->update([
            'last_synced_at' => now(),
            'emails_found'   => $account->emails_found + $newEmails,
            'tasks_created'  => $account->tasks_created + $totalTasks,
        ]);

        $this->info("  Done: {$newEmails} new emails, {$totalTasks} tasks created.");
    }

    // ─── Socket IMAP ────────────────────────────────────────────────────────

    private function imapConnect(string $host, int $port)
    {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ]]);

        if ($port === 993) {
            $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        } else {
            $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
        }

        if (!$sock) return "Kunne ikke forbinde til {$host}:{$port}: {$errstr}";

        stream_set_timeout($sock, 15);

        $greeting = fgets($sock, 4096);
        if (!$greeting || !str_contains($greeting, '* OK')) {
            fclose($sock);
            return 'Server svarede ikke: ' . trim((string) $greeting);
        }

        if ($port === 143) {
            fwrite($sock, "A1 STARTTLS\r\n");
            $tlsResp = fgets($sock, 4096);
            if (!str_contains((string) $tlsResp, 'A1 OK')) {
                fclose($sock);
                return 'STARTTLS fejlede: ' . trim((string) $tlsResp);
            }
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                return 'TLS-opgradering fejlede.';
            }
        }

        return $sock;
    }

    private function imapLogin($sock, string $user, string $pass)
    {
        $u = str_replace(['"', '\\'], ['\\"', '\\\\'], $user);
        $p = str_replace(['"', '\\'], ['\\"', '\\\\'], $pass);
        fwrite($sock, "A2 LOGIN \"{$u}\" \"{$p}\"\r\n");

        $response = '';
        while ($line = fgets($sock, 4096)) {
            $response .= $line;
            if (str_starts_with($line, 'A2 ')) break;
        }

        return str_contains($response, 'A2 OK') ? true : 'Login fejlede: ' . trim($response);
    }

    private function socketCmd($sock, string $cmd): string
    {
        fwrite($sock, $cmd . "\r\n");
        $tag = explode(' ', $cmd)[0];
        $response = '';
        while ($line = fgets($sock, 8192)) {
            $response .= $line;
            if (str_starts_with($line, $tag . ' ')) break;
        }
        return $response;
    }

    // ─── Parsing ─────────────────────────────────────────────────────────────

    private function parseHeader(string $raw, string $field): string
    {
        if (preg_match('/^' . preg_quote($field, '/') . ':\s*(.+?)(?=\r?\n(?!\s)|\z)/ims', $raw, $m)) {
            return mb_decode_mimeheader(trim($m[1]));
        }
        return '';
    }

    private function parseFrom(string $from): array
    {
        $from = trim($from);
        if (preg_match('/^"?(.+?)"?\s*<([^>]+)>/u', $from, $m)) {
            return [trim($m[2]), trim($m[1], '"')];
        }
        return [$from, $from];
    }

    private function extractFetchBody(string $raw): string
    {
        if (preg_match('/\{(\d+)\}\r?\n(.*)/s', $raw, $m)) {
            return substr($m[2], 0, (int) $m[1]);
        }
        return '';
    }

    // ─── AI + tasks ──────────────────────────────────────────────────────────

    private function analyseEmail(string $subject, string $snippet): array
    {
        $apiKey = config('services.mistral.key');
        if (!$apiKey) return ['relevant' => false, 'tasks' => []];

        $prompt = <<<PROMPT
Du hjælper en person igennem en skilsmisseproces. Analyser denne mail og afgør om den indeholder vigtige handlingspunkter, frister eller opgaver relateret til: skilsmisse, bodeling, børn/samvær, juridiske krav, økonomi, kommune eller forsikring.

Emne: {$subject}
Indhold: {$snippet}

Svar KUN med valid JSON i dette format:
{"relevant":true/false,"reason":"kort begrundelse","tasks":[{"title":"opgavetitel","description":"beskrivelse","due_date":"YYYY-MM-DD eller null","priority":"low/medium/high/critical","task_type":"legal/financial/document/communication/action"}]}

Inkluder kun tasks-arrayet hvis relevant er true. Maksimalt 3 opgaver.
PROMPT;

        $payload = json_encode([
            'model'       => 'mistral-small-latest',
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.1,
            'max_tokens'  => 400,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAuthorization: Bearer " . $apiKey . "\r\n",
                'content' => $payload,
                'timeout' => 20,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $response = @file_get_contents('https://api.mistral.ai/v1/chat/completions', false, $ctx);
        if (!$response) return ['relevant' => false, 'tasks' => []];

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        if (preg_match('/\{.*\}/s', $content, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) return $parsed;
        }

        return ['relevant' => false, 'tasks' => []];
    }

    private function createTasks(EmailAccount $account, string $subject, string $fromName, array $tasks): int
    {
        $created = 0;
        foreach ($tasks as $taskData) {
            if (empty($taskData['title'])) continue;
            Task::create([
                'user_id'      => $account->user_id,
                'case_id'      => null,
                'title'        => $taskData['title'],
                'description'  => ($taskData['description'] ?? '') . "\n\nFundet i mail fra {$fromName}: \"{$subject}\"",
                'task_type'    => $taskData['task_type'] ?? 'action',
                'priority'     => $taskData['priority'] ?? 'medium',
                'due_date'     => !empty($taskData['due_date']) ? $taskData['due_date'] : null,
                'status'       => 'pending',
                'ai_generated' => true,
                'ai_reasoning' => 'Automatisk fundet i indbakke',
            ]);
            $created++;
        }
        return $created;
    }
}
