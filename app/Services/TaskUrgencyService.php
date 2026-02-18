<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;

class TaskUrgencyService
{
    /**
     * Calculate urgency score for a task (0-100 scale).
     * Higher score = more urgent.
     */
    public function calculateUrgencyScore(Task $task): int
    {
        if ($task->status === 'completed') {
            return 0;
        }

        $score = 0;

        // Priority contributes up to 30 points
        $score += $this->getPriorityScore($task->priority);

        // Due date proximity contributes up to 50 points
        $score += $this->getDueDateScore($task->due_date);

        // Dependencies contribute up to 10 points
        $score += $this->getDependencyScore($task);

        // Task type contributes up to 10 points
        $score += $this->getTaskTypeScore($task->task_type);

        return min(100, max(0, $score));
    }

    /**
     * Get priority contribution to urgency (0-30 points).
     */
    private function getPriorityScore(string $priority): int
    {
        return match ($priority) {
            'critical' => 30,
            'high' => 20,
            'medium' => 10,
            'low' => 5,
            default => 0,
        };
    }

    /**
     * Get due date contribution to urgency (0-50 points).
     */
    private function getDueDateScore(?string $dueDate): int
    {
        if (!$dueDate) {
            return 0;
        }

        $due = Carbon::parse($dueDate);
        $now = Carbon::now();
        $daysUntilDue = $now->diffInDays($due, false);

        // Overdue tasks get maximum points
        if ($daysUntilDue < 0) {
            return 50;
        }

        // Due today
        if ($daysUntilDue === 0) {
            return 45;
        }

        // Due within 1 day
        if ($daysUntilDue <= 1) {
            return 40;
        }

        // Due within 3 days
        if ($daysUntilDue <= 3) {
            return 35;
        }

        // Due within 1 week
        if ($daysUntilDue <= 7) {
            return 25;
        }

        // Due within 2 weeks
        if ($daysUntilDue <= 14) {
            return 15;
        }

        // Due within 1 month
        if ($daysUntilDue <= 30) {
            return 8;
        }

        // More than 1 month away
        return 3;
    }

    /**
     * Get dependency contribution to urgency (0-10 points).
     */
    private function getDependencyScore(Task $task): int
    {
        // If this task is blocked by another task
        if ($task->depends_on_task_id) {
            $dependsOn = Task::find($task->depends_on_task_id);

            // If the blocking task is not completed, reduce urgency slightly
            if ($dependsOn && $dependsOn->status !== 'completed') {
                return 0;
            }

            // If blocking task is done, this becomes more urgent
            return 8;
        }

        // Check if other tasks depend on this one
        $dependentTasks = Task::where('depends_on_task_id', $task->id)
            ->where('status', '!=', 'completed')
            ->count();

        if ($dependentTasks > 0) {
            // This task blocks others, so it's more urgent
            return 10;
        }

        return 0;
    }

    /**
     * Get task type contribution to urgency (0-10 points).
     */
    private function getTaskTypeScore(?string $taskType): int
    {
        return match ($taskType) {
            'legal_deadline' => 10,
            'court_hearing' => 10,
            'document_submission' => 8,
            'meeting' => 6,
            'administrative' => 4,
            'research' => 2,
            default => 0,
        };
    }

    /**
     * Get urgency level label based on score.
     */
    public function getUrgencyLevel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'kritisk',
            $score >= 50 => 'høj',
            $score >= 30 => 'moderat',
            $score >= 15 => 'lav',
            default => 'minimal',
        };
    }

    /**
     * Get urgency color for UI display.
     */
    public function getUrgencyColor(int $score): string
    {
        return match (true) {
            $score >= 70 => 'red',
            $score >= 50 => 'orange',
            $score >= 30 => 'yellow',
            $score >= 15 => 'blue',
            default => 'gray',
        };
    }

    /**
     * Sort tasks by urgency.
     */
    public function sortByUrgency($tasks): array
    {
        $tasksWithScores = [];

        foreach ($tasks as $task) {
            $score = $this->calculateUrgencyScore($task);
            $tasksWithScores[] = [
                'task' => $task,
                'urgency_score' => $score,
                'urgency_level' => $this->getUrgencyLevel($score),
                'urgency_color' => $this->getUrgencyColor($score),
            ];
        }

        // Sort by urgency score descending
        usort($tasksWithScores, fn($a, $b) => $b['urgency_score'] <=> $a['urgency_score']);

        return $tasksWithScores;
    }

    /**
     * Get human-readable urgency description.
     */
    public function getUrgencyDescription(Task $task): string
    {
        $score = $this->calculateUrgencyScore($task);
        $level = $this->getUrgencyLevel($score);

        if ($task->status === 'completed') {
            return 'Afsluttet';
        }

        if (!$task->due_date) {
            return "Prioritet: {$task->priority} (ingen frist)";
        }

        $due = Carbon::parse($task->due_date);
        $now = Carbon::now();
        $daysUntilDue = $now->diffInDays($due, false);

        if ($daysUntilDue < 0) {
            $daysOverdue = abs($daysUntilDue);
            return "⚠️ Overskredet med {$daysOverdue} dag" . ($daysOverdue > 1 ? 'e' : '');
        }

        if ($daysUntilDue === 0) {
            return "⚡ Skal færdiggøres i dag";
        }

        if ($daysUntilDue === 1) {
            return "⚡ Forfald i morgen";
        }

        if ($daysUntilDue <= 3) {
            return "⚠️ Forfald om {$daysUntilDue} dage";
        }

        if ($daysUntilDue <= 7) {
            return "Forfald om {$daysUntilDue} dage";
        }

        return "Forfald: " . $due->locale('da')->isoFormat('D. MMM YYYY');
    }
}
