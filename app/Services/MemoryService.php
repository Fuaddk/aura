<?php

namespace App\Services;

use App\Models\UserMemory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    public function __construct(private KnowledgeService $knowledgeService) {}

    /**
     * Extract factual memories from a conversation and store them for the user.
     * Runs after HTTP response is sent — no delay for the user.
     */
    public function extractAndStore(int $userId, int $caseId, string $conversationText): void
    {
        try {
            $prompt = <<<PROMPT
Du er en assistent der udtrækker vigtige faktuelle oplysninger om brugeren fra en samtale.

Analyser samtalen nedenfor og udtrækker maks 5 korte, faktuelle oplysninger om brugeren.
Fokuser på: personlige facts (børn, bolig, arbejde, økonomi, juridisk situation, følelser/bekymringer).
Spring generelle spørgsmål og AI-svar over — kun bruger-specifikke facts.

Returner KUN en JSON-array med strings, f.eks.:
["Har to børn på 8 og 11 år", "Bor i ejerbolig i Aarhus", "Bekymret for samvær"]

Returner en tom array [] hvis der ingen klare facts er.

SAMTALE:
{$conversationText}
PROMPT;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.mistral.key'),
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post('https://api.mistral.ai/v1/chat/completions', [
                'model'       => 'mistral-small-latest',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.1,
                'max_tokens'  => 300,
            ]);

            if (!$response->successful()) {
                Log::warning('MemoryService: extraction API failed', ['status' => $response->status()]);
                return;
            }

            $content = $response->json('choices.0.message.content', '[]');

            // Parse the JSON array
            $facts = json_decode(trim($content), true);
            if (!is_array($facts) || empty($facts)) return;

            foreach ($facts as $fact) {
                $fact = trim((string) $fact);
                if (mb_strlen($fact) < 5) continue;

                $hash = hash('sha256', $userId . ':' . $fact);

                // Skip duplicates
                if (UserMemory::where('user_id', $userId)->where('content_hash', $hash)->exists()) {
                    continue;
                }

                // Create embedding
                $embedding = $this->knowledgeService->createEmbedding($fact);

                UserMemory::create([
                    'user_id'      => $userId,
                    'content'      => $fact,
                    'embedding'    => $embedding,
                    'category'     => 'general',
                    'content_hash' => $hash,
                    'case_id'      => $caseId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('MemoryService::extractAndStore failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Retrieve relevant memories for the current query and build a context string.
     */
    public function buildMemoryContext(int $userId, string $query, int $topK = 6): string
    {
        try {
            $memories = UserMemory::where('user_id', $userId)->whereNotNull('embedding')->get();
            if ($memories->isEmpty()) return '';

            $queryEmbedding = $this->knowledgeService->createEmbedding($query);

            if ($queryEmbedding) {
                // Semantic search
                $scored = [];
                foreach ($memories as $memory) {
                    $emb = $memory->embedding;
                    if (!is_array($emb) || empty($emb)) continue;

                    $score = $this->cosineSimilarity($queryEmbedding, $emb);
                    if ($score >= 0.2) {
                        $scored[] = ['memory' => $memory, 'score' => $score];
                    }
                }
                usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
                $top = array_slice($scored, 0, $topK);
                $selected = array_map(fn($s) => $s['memory'], $top);
            } else {
                // Fallback: recency
                $selected = $memories->sortByDesc('created_at')->take($topK)->values()->all();
            }

            if (empty($selected)) return '';

            $lines = array_map(fn($m) => '• ' . $m->content, $selected);
            return "─── HVAD JEG VED OM DIG ───\n" . implode("\n", $lines);
        } catch (\Throwable $e) {
            Log::error('MemoryService::buildMemoryContext failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0; $magA = 0; $magB = 0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }
        $magA = sqrt($magA); $magB = sqrt($magB);
        if ($magA == 0 || $magB == 0) return 0;
        return $dot / ($magA * $magB);
    }
}
