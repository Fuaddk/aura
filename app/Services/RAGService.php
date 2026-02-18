<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\Log;

class RAGService
{
    private const DEFAULT_TOP_K = 5;
    private const DEFAULT_SIMILARITY_THRESHOLD = 0.5;

    public function __construct(
        private MistralService $mistralService
    ) {}

    /**
     * Retrieve relevant knowledge chunks for a query
     *
     * @param string $query Search query
     * @param int $topK Number of results to return
     * @param float $similarityThreshold Minimum similarity score (0-1)
     * @return array Array of relevant chunks with metadata
     */
    public function retrieve(
        string $query,
        int $topK = self::DEFAULT_TOP_K,
        float $similarityThreshold = self::DEFAULT_SIMILARITY_THRESHOLD
    ): array {
        // Generate embedding for query
        $queryEmbedding = $this->mistralService->createEmbeddings($query);

        if (empty($queryEmbedding)) {
            Log::warning('Failed to generate query embedding', ['query' => $query]);
            return [];
        }

        // Retrieve all chunks (in production, use vector DB with indexing)
        $chunks = KnowledgeChunk::all();

        $results = [];

        foreach ($chunks as $chunk) {
            $chunkEmbedding = is_string($chunk->embedding)
                ? json_decode($chunk->embedding, true)
                : $chunk->embedding;

            if (!is_array($chunkEmbedding)) continue;

            $similarity = $this->cosineSimilarity($queryEmbedding, $chunkEmbedding);

            if ($similarity >= $similarityThreshold) {
                $results[] = [
                    'chunk' => $chunk,
                    'similarity' => $similarity,
                    'content' => $chunk->content,
                    'source' => $chunk->source_title,
                    'url' => $chunk->source_url,
                ];
            }
        }

        // Sort by similarity (descending) and take top K
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $topK);
    }

    /**
     * Format retrieved chunks for context injection
     *
     * @param array $chunks Retrieved chunks from retrieve()
     * @return string Formatted context string
     */
    public function formatContext(array $chunks): string
    {
        if (empty($chunks)) {
            return '';
        }

        $context = "Her er relevant information fra retsinformation:\n\n";

        foreach ($chunks as $i => $chunk) {
            $context .= sprintf(
                "--- Kilde %d: %s ---\n%s\n\n",
                $i + 1,
                $chunk['source'] ?? 'Ukendt kilde',
                $chunk['content']
            );
        }

        return $context;
    }

    /**
     * Build augmented prompt with RAG context
     *
     * @param string $userQuery User's question/message
     * @param string $systemPrompt Base system prompt
     * @param int $topK Number of chunks to retrieve
     * @return array Messages array with augmented context
     */
    public function buildAugmentedPrompt(
        string $userQuery,
        string $systemPrompt,
        int $topK = self::DEFAULT_TOP_K
    ): array {
        $chunks = $this->retrieve($userQuery, $topK);
        $context = $this->formatContext($chunks);

        $augmentedPrompt = $systemPrompt;

        if (!empty($context)) {
            $augmentedPrompt .= "\n\n" . $context;
        }

        return [
            [
                'role' => 'system',
                'content' => $augmentedPrompt
            ],
            [
                'role' => 'user',
                'content' => $userQuery
            ]
        ];
    }

    /**
     * Get sources/citations from retrieved chunks
     *
     * @param array $chunks Retrieved chunks
     * @return array Array of sources with title and URL
     */
    public function getSources(array $chunks): array
    {
        $sources = [];

        foreach ($chunks as $chunk) {
            $sources[] = [
                'title' => $chunk['source'] ?? 'Ukendt',
                'url' => $chunk['url'] ?? null,
                'similarity' => round($chunk['similarity'], 3),
            ];
        }

        return $sources;
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param array $a First vector
     * @param array $b Second vector
     * @return float Similarity score (0-1)
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            Log::warning('Vector dimension mismatch', [
                'a_dim' => count($a),
                'b_dim' => count($b)
            ]);
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Index new content into knowledge base
     *
     * @param string $content Content to index
     * @param array $metadata Metadata (source_url, source_title, category, etc.)
     * @return KnowledgeChunk|null Created chunk or null on failure
     */
    public function indexContent(string $content, array $metadata = []): ?KnowledgeChunk
    {
        // Generate embedding
        $embedding = $this->mistralService->createEmbeddings($content);

        if (empty($embedding)) {
            Log::error('Failed to generate embedding for content', [
                'content_preview' => mb_substr($content, 0, 100)
            ]);
            return null;
        }

        try {
            return KnowledgeChunk::create([
                'content' => $content,
                'embedding' => json_encode($embedding),
                'source_url' => $metadata['source_url'] ?? null,
                'source_title' => $metadata['source_title'] ?? null,
                'category' => $metadata['category'] ?? null,
                'token_count' => str_word_count($content),
                'content_hash' => md5($content),
                'scraped_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to index content', [
                'error' => $e->getMessage(),
                'metadata' => $metadata
            ]);
            return null;
        }
    }
}
