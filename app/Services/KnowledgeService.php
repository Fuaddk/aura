<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KnowledgeService
{
    /**
     * All knowledge source URLs with categories.
     */
    public static function getSources(): array
    {
        return [
            // Lovgivning
            ['url' => 'https://www.retsinformation.dk/eli/lta/2019/772', 'category' => 'forældremyndighed', 'title' => 'Forældreansvarsloven'],
            ['url' => 'https://www.retsinformation.dk/eli/lta/2016/193', 'category' => 'skilsmisse', 'title' => 'Ægteskabsloven'],

            // Familieretshuset
            ['url' => 'https://familieretshuset.dk/emner/brud-i-familien/for-separerede/separation/', 'category' => 'separation', 'title' => 'Separation - Familieretshuset'],
            ['url' => 'https://familieretshuset.dk/emner/brud-i-familien/for-gifte/ophoersdato-og-bodeling/', 'category' => 'bodeling', 'title' => 'Ophørsdato og bodeling'],

            // Borger.dk
            ['url' => 'https://www.borger.dk/familie-og-boern/Skilsmisse-og-familiebrud/Separation-og-skilsmisse', 'category' => 'skilsmisse', 'title' => 'Separation og skilsmisse - Borger.dk'],
            ['url' => 'https://www.borger.dk/familie-og-boern/Udsatte-boern-og-unge/Boern-i-mistrivsel', 'category' => 'boern', 'title' => 'Børn i mistrivsel - Borger.dk'],

            // Vold og sikkerhed
            ['url' => 'https://levudenvold.dk/viden-om-vold/former-for-vold/hvad-er-vold/', 'category' => 'vold', 'title' => 'Former for vold - Lev Uden Vold'],
            ['url' => 'https://politi.dk/anmeld-kriminalitet/stalking-psykisk-vold-og-vold-i-naere-relationer/anmeld-trusler', 'category' => 'vold', 'title' => 'Anmeld trusler - Politiet'],

            // Straffeloven
            ['url' => 'https://danskelove.dk/straffeloven/266', 'category' => 'vold', 'title' => 'Straffeloven §266 - Trusler'],

            // Socialstyrelsen
            ['url' => 'https://www.sbst.dk/boern/vold-og-seksuelle-overgreb', 'category' => 'boern', 'title' => 'Vold og seksuelle overgreb mod børn'],
        ];
    }

    /**
     * Scrape a URL and return plain text content.
     */
    public function scrapeUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'AuraBot/1.0 (Danish Family Law Knowledge)'])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("Knowledge scrape failed for {$url}", ['status' => $response->status()]);
                return null;
            }

            $html = $response->body();
            return $this->htmlToText($html);
        } catch (\Exception $e) {
            Log::error("Knowledge scrape exception for {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Convert HTML to clean text.
     */
    private function htmlToText(string $html): string
    {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<nav\b[^>]*>.*?<\/nav>/si', '', $html);
        $html = preg_replace('/<footer\b[^>]*>.*?<\/footer>/si', '', $html);
        $html = preg_replace('/<header\b[^>]*>.*?<\/header>/si', '', $html);

        // Convert common block elements to newlines
        $html = preg_replace('/<(br|p|div|h[1-6]|li|tr)[^>]*>/i', "\n", $html);

        // Strip remaining tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Split text into chunks of approximately $maxTokens tokens.
     */
    public function chunkText(string $text, int $maxTokens = 500): array
    {
        // Split by double-newlines (paragraphs) first
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;

            $paraTokens = $this->estimateTokens($paragraph);

            // If single paragraph is too large, split by sentences
            if ($paraTokens > $maxTokens) {
                if ($currentChunk) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                    $currentTokens = 0;
                }

                $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
                foreach ($sentences as $sentence) {
                    $sentTokens = $this->estimateTokens($sentence);
                    if ($currentTokens + $sentTokens > $maxTokens && $currentChunk) {
                        $chunks[] = trim($currentChunk);
                        $currentChunk = '';
                        $currentTokens = 0;
                    }
                    $currentChunk .= ' ' . $sentence;
                    $currentTokens += $sentTokens;
                }
                continue;
            }

            if ($currentTokens + $paraTokens > $maxTokens && $currentChunk) {
                $chunks[] = trim($currentChunk);
                $currentChunk = '';
                $currentTokens = 0;
            }

            $currentChunk .= "\n\n" . $paragraph;
            $currentTokens += $paraTokens;
        }

        if (trim($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        // Filter out very short chunks (less than 50 chars - likely navigation remnants)
        return array_values(array_filter($chunks, fn($c) => mb_strlen($c) >= 50));
    }

    /**
     * Rough token estimation (~1 token per 4 chars for Danish text).
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Create embedding for a text using Mistral Embeddings API.
     */
    public function createEmbedding(string $text): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.mistral.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.mistral.ai/v1/embeddings', [
                'model' => 'mistral-embed',
                'input' => [$text],
            ]);

            if ($response->successful()) {
                return $response->json('data.0.embedding');
            }

            Log::error('Mistral embedding API error', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Mistral embedding exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create embeddings for multiple texts in batch.
     */
    public function createEmbeddings(array $texts): array
    {
        if (empty($texts)) return [];

        // Mistral supports batch embeddings - send up to 16 at a time
        $results = [];
        $batches = array_chunk($texts, 16);

        foreach ($batches as $batch) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('services.mistral.key'),
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post('https://api.mistral.ai/v1/embeddings', [
                    'model' => 'mistral-embed',
                    'input' => array_values($batch),
                ]);

                if ($response->successful()) {
                    $data = $response->json('data');
                    foreach ($data as $item) {
                        $results[] = $item['embedding'];
                    }
                } else {
                    Log::error('Mistral batch embedding error', ['status' => $response->status()]);
                    // Fill with nulls for failed batch
                    $results = array_merge($results, array_fill(0, count($batch), null));
                }
            } catch (\Exception $e) {
                Log::error('Mistral batch embedding exception', ['error' => $e->getMessage()]);
                $results = array_merge($results, array_fill(0, count($batch), null));
            }

            // Small delay between batches to respect rate limits
            if (count($batches) > 1) {
                usleep(200000); // 200ms
            }
        }

        return $results;
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Retrieve the most relevant knowledge chunks for a query.
     */
    public function retrieve(string $query, int $topK = 5, float $minScore = 0.3): array
    {
        // Create embedding for the query
        $queryEmbedding = $this->createEmbedding($query);
        if (!$queryEmbedding) {
            return [];
        }

        // Get all chunks with embeddings
        $chunks = KnowledgeChunk::whereNotNull('embedding')->get();

        // Calculate similarity scores
        $scored = [];
        foreach ($chunks as $chunk) {
            $embedding = $chunk->embedding;
            if (!is_array($embedding) || empty($embedding)) continue;

            $score = $this->cosineSimilarity($queryEmbedding, $embedding);
            if ($score >= $minScore) {
                $scored[] = [
                    'chunk' => $chunk,
                    'score' => $score,
                ];
            }
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return top K
        return array_slice($scored, 0, $topK);
    }

    /**
     * Build a context string from retrieved chunks for injection into the prompt.
     */
    public function buildContext(string $query, int $topK = 5): string
    {
        $results = $this->retrieve($query, $topK);

        if (empty($results)) {
            return '';
        }

        $context = "RELEVANT VIDEN FRA OFFICIELLE DANSKE KILDER:\n\n";

        foreach ($results as $result) {
            $chunk = $result['chunk'];
            $context .= "--- Kilde: {$chunk->source_title} ({$chunk->category}) ---\n";
            $context .= $chunk->content . "\n\n";
        }

        $context .= "Brug ovenstående viden til at give præcise, opdaterede svar. Henvis gerne til kilderne.\n";

        return $context;
    }

    /**
     * Process a single source: scrape, chunk, embed, and store.
     */
    public function processSource(array $source, callable $onProgress = null): int
    {
        $url = $source['url'];
        $category = $source['category'];
        $title = $source['title'];

        if ($onProgress) $onProgress("Scraper: {$url}");

        $text = $this->scrapeUrl($url);
        if (!$text || mb_strlen($text) < 100) {
            if ($onProgress) $onProgress("  ⚠ Kunne ikke scrape eller for lidt indhold");
            return 0;
        }

        if ($onProgress) $onProgress("  Tekst hentet: " . mb_strlen($text) . " tegn");

        // Chunk the text
        $chunks = $this->chunkText($text);
        if (empty($chunks)) {
            if ($onProgress) $onProgress("  ⚠ Ingen chunks genereret");
            return 0;
        }

        if ($onProgress) $onProgress("  Chunks: " . count($chunks));

        // Check which chunks have changed
        $newChunks = [];
        $hashes = [];
        foreach ($chunks as $i => $chunkText) {
            $hash = hash('sha256', $chunkText);
            $hashes[] = $hash;

            $existing = KnowledgeChunk::where('source_url', $url)
                ->where('content_hash', $hash)
                ->first();

            if (!$existing) {
                $newChunks[$i] = $chunkText;
            }
        }

        // Delete old chunks from this URL that no longer exist
        KnowledgeChunk::where('source_url', $url)
            ->whereNotIn('content_hash', $hashes)
            ->delete();

        if (empty($newChunks)) {
            if ($onProgress) $onProgress("  ✓ Ingen ændringer - allerede opdateret");
            return 0;
        }

        if ($onProgress) $onProgress("  Nye/ændrede chunks: " . count($newChunks) . " - opretter embeddings...");

        // Create embeddings in batch
        $embeddings = $this->createEmbeddings(array_values($newChunks));

        // Store chunks
        $stored = 0;
        $chunkTexts = array_values($newChunks);
        $chunkIndices = array_keys($newChunks);

        foreach ($chunkTexts as $j => $chunkText) {
            $embedding = $embeddings[$j] ?? null;
            $originalIndex = $chunkIndices[$j];
            $hash = $hashes[$originalIndex];

            KnowledgeChunk::create([
                'source_url' => $url,
                'source_title' => $title,
                'content' => $chunkText,
                'embedding' => $embedding,
                'category' => $category,
                'chunk_index' => $originalIndex,
                'token_count' => $this->estimateTokens($chunkText),
                'content_hash' => $hash,
                'scraped_at' => now(),
            ]);
            $stored++;
        }

        if ($onProgress) $onProgress("  ✓ {$stored} chunks gemt med embeddings");
        return $stored;
    }
}
