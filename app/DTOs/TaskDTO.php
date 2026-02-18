<?php

namespace App\DTOs;

use Carbon\Carbon;

class TaskDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $taskType,
        public readonly string $priority,
        public readonly string $status,
        public readonly ?Carbon $dueDate,
        public readonly ?int $estimatedDurationMinutes,
        public readonly ?int $caseId,
        public readonly int $userId,
        public readonly bool $aiGenerated = false,
        public readonly ?string $aiReasoning = null,
        public readonly ?float $aiConfidenceScore = null,
        public readonly ?int $dependsOnTaskId = null,
        public readonly ?array $metadata = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            title: $data['title'],
            description: $data['description'] ?? null,
            taskType: $data['task_type'] ?? 'action',
            priority: $data['priority'] ?? 'medium',
            status: $data['status'] ?? 'pending',
            dueDate: isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
            estimatedDurationMinutes: $data['estimated_duration_minutes'] ?? null,
            caseId: $data['case_id'] ?? null,
            userId: $data['user_id'],
            aiGenerated: $data['ai_generated'] ?? false,
            aiReasoning: $data['ai_reasoning'] ?? null,
            aiConfidenceScore: $data['ai_confidence_score'] ?? null,
            dependsOnTaskId: $data['depends_on_task_id'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Convert DTO to array for database
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'task_type' => $this->taskType,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'estimated_duration_minutes' => $this->estimatedDurationMinutes,
            'case_id' => $this->caseId,
            'user_id' => $this->userId,
            'ai_generated' => $this->aiGenerated,
            'ai_reasoning' => $this->aiReasoning,
            'ai_confidence_score' => $this->aiConfidenceScore,
            'depends_on_task_id' => $this->dependsOnTaskId,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Calculate urgency level based on due date
     */
    public function getUrgencyLevel(): string
    {
        if (!$this->dueDate) {
            return 'ok';
        }

        $days = now()->diffInDays($this->dueDate, false);

        if ($days <= 3) return 'urgent';
        if ($days <= 7) return 'warning';
        if ($days <= 14) return 'soon';

        return 'ok';
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->dueDate) {
            return false;
        }

        return $this->dueDate->isPast() && $this->status !== 'completed';
    }
}
