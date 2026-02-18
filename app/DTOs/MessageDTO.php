<?php

namespace App\DTOs;

use Carbon\Carbon;

class MessageDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $role,
        public readonly string $content,
        public readonly ?int $caseId,
        public readonly int $userId,
        public readonly ?array $retrievedChunks = null,
        public readonly ?string $modelUsed = null,
        public readonly ?int $tokensUsed = null,
        public readonly ?int $responseTimeMs = null,
        public readonly ?array $metadata = null,
        public readonly ?Carbon $createdAt = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            role: $data['role'],
            content: $data['content'],
            caseId: $data['case_id'] ?? null,
            userId: $data['user_id'],
            retrievedChunks: $data['retrieved_chunks'] ?? null,
            modelUsed: $data['model_used'] ?? null,
            tokensUsed: $data['tokens_used'] ?? null,
            responseTimeMs: $data['response_time_ms'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : null,
        );
    }

    /**
     * Convert DTO to array for database
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'case_id' => $this->caseId,
            'user_id' => $this->userId,
            'retrieved_chunks' => $this->retrievedChunks,
            'model_used' => $this->modelUsed,
            'tokens_used' => $this->tokensUsed,
            'response_time_ms' => $this->responseTimeMs,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if message is from user
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if message is from assistant
     */
    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Check if message is from system
     */
    public function isSystem(): bool
    {
        return $this->role === 'system';
    }

    /**
     * Get preview of content (first N characters)
     */
    public function getPreview(int $length = 100): string
    {
        return mb_substr($this->content, 0, $length) . (mb_strlen($this->content) > $length ? '...' : '');
    }

    /**
     * Check if message has RAG sources
     */
    public function hasSources(): bool
    {
        return !empty($this->retrievedChunks);
    }

    /**
     * Get formatted sources from retrieved chunks
     */
    public function getSources(): array
    {
        if (!$this->hasSources()) {
            return [];
        }

        return array_map(function ($chunk) {
            return [
                'title' => $chunk['source'] ?? 'Ukendt kilde',
                'url' => $chunk['url'] ?? null,
                'similarity' => $chunk['similarity'] ?? null,
            ];
        }, $this->retrievedChunks);
    }

    /**
     * Estimate tokens (rough approximation)
     */
    public function estimateTokens(): int
    {
        // Rough estimate: ~1 token per 4 characters
        return (int) (mb_strlen($this->content) / 4);
    }
}
