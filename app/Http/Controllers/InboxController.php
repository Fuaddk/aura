<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\EmailAccount;
use App\Models\InboxEmail;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    private const IMAP_PRESETS = [
        'gmail'   => ['host' => 'imap.gmail.com',           'port' => 993],
        'outlook' => ['host' => 'outlook.office365.com',    'port' => 993],
        'yahoo'   => ['host' => 'imap.mail.yahoo.com',      'port' => 993],
    ];

    public function index(Request $request): Response
    {
        $user = auth()->user();

        $cases = CaseModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        $accounts = EmailAccount::where('user_id', $user->id)
            ->withCount('emails')
            ->get();

        $emails = InboxEmail::where('user_id', $user->id)
            ->where('is_relevant', true)
            ->orderBy('received_at', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('Inbox', [
            'cases'    => $cases,
            'accounts' => $accounts,
            'emails'   => $emails,
        ]);
    }

    public function connect(Request $request)
    {
        $validated = $request->validate([
            'provider'  => 'required|string',
            'email'     => 'required|email',
            'password'  => 'required|string',
            'imap_host' => 'nullable|string',
            'imap_port' => 'nullable|integer|min:1|max:65535',
        ]);

        $preset   = self::IMAP_PRESETS[$validated['provider']] ?? null;
        $imapHost = rtrim($validated['imap_host'] ?: ($preset['host'] ?? ''), '. ');
        $imapPort = (int) ($validated['imap_port'] ?: ($preset['port'] ?? 993));

        if (!$imapHost) {
            return back()->withErrors(['imap_host' => 'Angiv IMAP-server for denne udbyder.']);
        }

        // Test connection via raw socket (bypasses PHP IMAP extension quirks on Windows)
        $result = $this->socketImapLogin($imapHost, $imapPort, $validated['email'], $validated['password']);

        if ($result !== true) {
            $msg = $result; // human-readable error string

            if ($validated['provider'] === 'gmail') {
                $msg .= ' Gmail kræver et App-adgangskode — se google.com/settings/security.';
            } elseif ($validated['provider'] === 'outlook') {
                $msg .= ' Outlook kræver muligvis et App-adgangskode hvis 2-trins-godkendelse er aktiveret.';
            }

            return back()->withErrors(['password' => $msg]);
        }

        // Save / update account
        $existing = EmailAccount::where('user_id', auth()->id())
            ->where('email', $validated['email'])
            ->first();

        if ($existing) {
            $existing->update([
                'imap_host'     => $imapHost,
                'imap_port'     => $imapPort,
                'imap_password' => $validated['password'],
                'is_active'     => true,
            ]);
        } else {
            EmailAccount::create([
                'user_id'       => auth()->id(),
                'provider'      => $validated['provider'],
                'email'         => $validated['email'],
                'imap_host'     => $imapHost,
                'imap_port'     => $imapPort,
                'imap_password' => $validated['password'],
                'is_active'     => true,
            ]);
        }

        return redirect()->route('inbox.index')->with('success', 'Mailkonto tilsluttet!');
    }

    public function toggleAutoSync(EmailAccount $account)
    {
        if ($account->user_id !== auth()->id()) {
            abort(403);
        }

        $account->update(['auto_sync' => !$account->auto_sync]);

        return back();
    }

    public function disconnect(EmailAccount $account)
    {
        if ($account->user_id !== auth()->id()) {
            abort(403);
        }

        $account->emails()->delete();
        $account->delete();

        return redirect()->route('inbox.index')->with('success', 'Mailkonto fjernet.');
    }

    public function sync(EmailAccount $account)
    {
        if ($account->user_id !== auth()->id()) {
            abort(403);
        }

        $sock = $this->socketImapConnect($account->imap_host, $account->imap_port);
        if (!is_resource($sock)) {
            return response()->json(['error' => $sock], 500);
        }

        $loginResult = $this->socketSendLogin($sock, $account->email, $account->imap_password);
        if ($loginResult !== true) {
            fclose($sock);
            return response()->json(['error' => $loginResult], 500);
        }

        // SELECT INBOX
        $this->socketCmd($sock, 'A3 SELECT INBOX'); 

        // SEARCH emails from last 30 days
        $since = date('d-M-Y', strtotime('-30 days'));
        $resp  = $this->socketCmd($sock, "A4 SEARCH SINCE {$since}");

        $uids = [];
        foreach (explode("\n", $resp) as $line) {
            if (str_starts_with(trim($line), '* SEARCH')) {
                $parts = explode(' ', trim($line));
                array_shift($parts); // *
                array_shift($parts); // SEARCH
                $uids = array_filter($parts, 'is_numeric');
                $uids = array_map('intval', $uids);
                break;
            }
        }

        if (empty($uids)) {
            fclose($sock);
            $account->update(['last_synced_at' => now()]);
            return response()->json(['found' => 0, 'new' => 0, 'tasks' => 0]);
        }

        // Newest first, max 30
        rsort($uids);
        $uids = array_slice($uids, 0, 30);

        $newEmails  = 0;
        $totalTasks = 0;

        foreach ($uids as $uid) {
            $uidStr = (string) $uid;

            if (InboxEmail::where('email_account_id', $account->id)->where('message_uid', $uidStr)->exists()) {
                continue;
            }

            // FETCH header
            $headerRaw = $this->socketCmd($sock, "A5 FETCH {$uid} (BODY[HEADER.FIELDS (FROM SUBJECT DATE)])");
            $subject    = $this->parseHeader($headerRaw, 'Subject') ?: '(Ingen emne)';
            $fromRaw    = $this->parseHeader($headerRaw, 'From') ?: '';
            $dateRaw    = $this->parseHeader($headerRaw, 'Date') ?: '';

            [$fromEmail, $fromName] = $this->parseFrom($fromRaw);
            $receivedAt = $dateRaw ? date('Y-m-d H:i:s', strtotime($dateRaw)) : now()->toDateTimeString();

            // FETCH body (plain text, first 2000 chars)
            $bodyRaw = $this->socketCmd($sock, "A6 FETCH {$uid} (BODY[1])");
            $body    = $this->extractFetchBody($bodyRaw);
            $snippet = mb_substr(strip_tags($body), 0, 500);

            $analysis     = $this->analyseEmail($subject, $snippet);
            $isRelevant   = !empty($analysis['relevant']);
            $tasksCreated = 0;

            if ($isRelevant && !empty($analysis['tasks'])) {
                $tasksCreated = $this->createTasksFromEmail($account, $subject, $fromName, $analysis['tasks']);
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
        }

        $this->socketCmd($sock, 'A7 LOGOUT');
        fclose($sock);

        $account->update([
            'last_synced_at' => now(),
            'emails_found'   => $account->emails_found + $newEmails,
            'tasks_created'  => $account->tasks_created + $totalTasks,
        ]);

        return response()->json([
            'found' => count($uids),
            'new'   => $newEmails,
            'tasks' => $totalTasks,
        ]);
    }

    // ─── Socket IMAP helpers ─────────────────────────────────────────────────

    /** Returns socket resource on success, or error string on failure. */
    private function socketImapConnect(string $host, int $port)
    {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ]]);

        $timeout = 15;

        if ($port === 993) {
            $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        } else {
            $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
        }

        if (!$sock) {
            return "Kunne ikke nå mailserveren ({$host}:{$port}): {$errstr}";
        }

        stream_set_timeout($sock, $timeout);

        // Read greeting
        $greeting = fgets($sock, 4096);
        if (!$greeting || !str_contains($greeting, '* OK')) {
            fclose($sock);
            return 'Mailserveren svarede ikke korrekt: ' . trim((string) $greeting);
        }

        // STARTTLS upgrade for port 143
        if ($port === 143) {
            fwrite($sock, "A1 STARTTLS\r\n");
            $tlsResp = fgets($sock, 4096);
            if (!str_contains((string) $tlsResp, 'A1 OK')) {
                fclose($sock);
                return 'STARTTLS ikke understøttet af serveren: ' . trim((string) $tlsResp);
            }
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                return 'TLS-opgradering fejlede. Prøv port 993 i stedet.';
            }
        }

        return $sock;
    }

    /** Returns true on success, or error string on failure. */
    private function socketSendLogin($sock, string $user, string $pass)
    {
        $u    = str_replace(['"', '\\'], ['\\"', '\\\\'], $user);
        $p    = str_replace(['"', '\\'], ['\\"', '\\\\'], $pass);
        fwrite($sock, "A2 LOGIN \"{$u}\" \"{$p}\"\r\n");

        $response = '';
        while ($line = fgets($sock, 4096)) {
            $response .= $line;
            if (str_starts_with($line, 'A2 ')) break;
        }

        if (str_contains($response, 'A2 OK')) {
            return true;
        }

        if (stripos($response, 'AUTHENTICATIONFAILED') !== false
            || stripos($response, 'Authentication failed') !== false
            || stripos($response, 'Invalid credentials') !== false
            || stripos($response, 'LOGIN failed') !== false
            || str_contains($response, 'A2 NO')) {
            return 'Forkert e-mail eller adgangskode. Tjek dine oplysninger og prøv igen.';
        }

        return 'Login fejlede: ' . trim($response);
    }

    /** Convenience: connect + login, returns true or error string. */
    private function socketImapLogin(string $host, int $port, string $user, string $pass)
    {
        $sock = $this->socketImapConnect($host, $port);
        if (!is_resource($sock)) return $sock;

        $login = $this->socketSendLogin($sock, $user, $pass);
        $this->socketCmd($sock, 'AX LOGOUT');
        fclose($sock);

        return $login;
    }

    /** Send a command and collect the tagged response. */
    private function socketCmd($sock, string $cmd): string
    {
        fwrite($sock, $cmd . "\r\n");
        $tag      = explode(' ', $cmd)[0];
        $response = '';
        while ($line = fgets($sock, 8192)) {
            $response .= $line;
            if (str_starts_with($line, $tag . ' ')) break;
        }
        return $response;
    }

    // ─── Email parsing helpers ───────────────────────────────────────────────

    private function parseHeader(string $raw, string $field): string
    {
        if (preg_match('/^' . preg_quote($field, '/') . ':\s*(.+?)(?=\r?\n(?!\s)|\z)/ims', $raw, $m)) {
            // Decode MIME encoded-words
            return $this->decodeMimeStr(trim($m[1]));
        }
        return '';
    }

    private function decodeMimeStr(string $str): string
    {
        return mb_decode_mimeheader($str);
    }

    private function parseFrom(string $from): array
    {
        $from = trim($from);
        // "Name" <email@example.com>
        if (preg_match('/^"?(.+?)"?\s*<([^>]+)>/u', $from, $m)) {
            return [trim($m[2]), trim($m[1], '"')];
        }
        // plain email
        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return [$from, $from];
        }
        return [$from, $from];
    }

    private function extractFetchBody(string $raw): string
    {
        // Strip the IMAP FETCH response wrapper, keep the literal body
        if (preg_match('/\{(\d+)\}\r?\n(.*)/s', $raw, $m)) {
            return substr($m[2], 0, (int) $m[1]);
        }
        return '';
    }

    // ─── AI analysis ────────────────────────────────────────────────────────

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
            'model'       => 'mistral-large-latest',
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.1,
            'max_tokens'  => 400,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
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

    private function createTasksFromEmail(EmailAccount $account, string $subject, string $fromName, array $tasks): int
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
