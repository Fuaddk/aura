<?php

namespace App\DTOs;

use Carbon\Carbon;

class DocumentDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $filename,
        public readonly ?string $originalFilename,
        public readonly string $mimeType,
        public readonly int $fileSizeBytes,
        public readonly string $storagePath,
        public readonly string $documentType,
        public readonly ?string $documentCategory,
        public readonly string $processingStatus,
        public readonly ?string $extractedText,
        public readonly ?string $aiSummary,
        public readonly ?array $aiKeyPoints,
        public readonly ?array $aiEntities,
        public readonly ?int $caseId,
        public readonly ?int $taskId,
        public readonly int $userId,
        public readonly bool $encrypted = false,
        public readonly ?string $encryptionKeyId = null,
        public readonly ?Carbon $processedAt = null,
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            filename: $data['filename'],
            originalFilename: $data['original_filename'] ?? null,
            mimeType: $data['mime_type'],
            fileSizeBytes: $data['file_size_bytes'],
            storagePath: $data['storage_path'],
            documentType: $data['document_type'] ?? 'upload',
            documentCategory: $data['document_category'] ?? null,
            processingStatus: $data['processing_status'] ?? 'pending',
            extractedText: $data['extracted_text'] ?? null,
            aiSummary: $data['ai_summary'] ?? null,
            aiKeyPoints: $data['ai_key_points'] ?? null,
            aiEntities: $data['ai_entities'] ?? null,
            caseId: $data['case_id'] ?? null,
            taskId: $data['task_id'] ?? null,
            userId: $data['user_id'],
            encrypted: $data['encrypted'] ?? false,
            encryptionKeyId: $data['encryption_key_id'] ?? null,
            processedAt: isset($data['processed_at']) ? Carbon::parse($data['processed_at']) : null,
            errorMessage: $data['error_message'] ?? null,
        );
    }

    /**
     * Convert DTO to array for database
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'original_filename' => $this->originalFilename,
            'mime_type' => $this->mimeType,
            'file_size_bytes' => $this->fileSizeBytes,
            'storage_path' => $this->storagePath,
            'document_type' => $this->documentType,
            'document_category' => $this->documentCategory,
            'processing_status' => $this->processingStatus,
            'extracted_text' => $this->extractedText,
            'ai_summary' => $this->aiSummary,
            'ai_key_points' => $this->aiKeyPoints,
            'ai_entities' => $this->aiEntities,
            'case_id' => $this->caseId,
            'task_id' => $this->taskId,
            'user_id' => $this->userId,
            'encrypted' => $this->encrypted,
            'encryption_key_id' => $this->encryptionKeyId,
            'processed_at' => $this->processedAt?->format('Y-m-d H:i:s'),
            'error_message' => $this->errorMessage,
        ];
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedSize(): string
    {
        $bytes = $this->fileSizeBytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if document is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Check if document is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    /**
     * Check if processing completed successfully
     */
    public function isProcessed(): bool
    {
        return $this->processingStatus === 'completed';
    }

    /**
     * Check if processing failed
     */
    public function hasFailed(): bool
    {
        return $this->processingStatus === 'failed';
    }
}
