<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Document;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $user     = auth()->user();
        $category = $request->query('category');
        $now      = now();

        $cases = CaseModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        // Single query: fetch ALL user tasks (select only needed columns)
        $allUserTasks = $user->tasks()
            ->select('id', 'user_id', 'title', 'description', 'task_type', 'priority', 'due_date', 'status', 'completed_at', 'ai_generated', 'depends_on_task_id', 'metadata', 'created_at')
            ->get();

        // Split in-memory
        $activeTasks    = $allUserTasks->where('status', '!=', 'completed');
        $totalTasks     = $allUserTasks->count();
        $completedCount = $allUserTasks->where('status', 'completed')->count();
        $completionPercentage = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100) : 0;

        $categoryCounts        = [];
        $categoryUrgency       = [];
        $categoryUrgencyCounts = [];

        foreach ($activeTasks as $task) {
            $type = $task->task_type ?: 'action';
            $categoryCounts[$type] = ($categoryCounts[$type] ?? 0) + 1;

            $urgency = 'ok';
            if ($task->due_date) {
                $days = $now->diffInDays($task->due_date, false);
                if ($days <= 3) {
                    $urgency = 'urgent';
                } elseif ($days <= 7) {
                    $urgency = 'warning';
                } elseif ($days <= 14) {
                    $urgency = 'soon';
                }
            }

            $current = $categoryUrgency[$type] ?? null;
            if ($current === null || $urgency === 'urgent' || ($urgency === 'warning' && $current !== 'urgent') || ($urgency === 'soon' && $current === 'ok')) {
                $categoryUrgency[$type] = $urgency;
            }

            if (!isset($categoryUrgencyCounts[$type])) {
                $categoryUrgencyCounts[$type] = ['urgent' => 0, 'warning' => 0, 'soon' => 0];
            }
            if (in_array($urgency, ['urgent', 'warning', 'soon'])) {
                $categoryUrgencyCounts[$type][$urgency]++;
            }
        }

        // Urgent tasks — filter from already-loaded collection (no extra query)
        $urgentTasks = $activeTasks
            ->filter(fn ($t) => $t->due_date && $t->due_date->lte($now->copy()->addDays(14)))
            ->sortBy('due_date')
            ->take(5)
            ->values();

        // Category-filtered tasks — also from the already-loaded collection
        $tasks = [];
        if ($category) {
            $tasks = $allUserTasks
                ->where('task_type', $category)
                ->sortBy([
                    ['status', 'asc'],
                    [fn ($t) => $t->due_date ? 0 : 1, 'asc'],
                    ['due_date', 'asc'],
                    ['priority', 'desc'],
                ])
                ->values();
        }

        return Inertia::render('Tasks', [
            'tasks'                  => $tasks,
            'urgentTasks'            => $urgentTasks,
            'cases'                  => $cases,
            'category'               => $category,
            'categoryCounts'         => $categoryCounts,
            'categoryUrgency'        => $categoryUrgency,
            'categoryUrgencyCounts'  => $categoryUrgencyCounts,
            'completionPercentage'   => $completionPercentage,
        ]);
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $user = auth()->user();

        if ($task->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        $task->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'task' => $task->fresh(),
        ]);
    }

    public function saveDocument(Request $request, Task $task): JsonResponse
    {
        $user = auth()->user();

        if ($task->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        // Generate PDF from content
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
            $this->buildDocumentHtml($validated['title'], $validated['content'])
        );
        $pdf->setPaper('a4');
        $pdfContent = $pdf->output();

        $slug     = \Str::slug($validated['title']);
        $filename = $slug . '.pdf';
        $path     = 'documents/' . $user->id . '/' . $filename;

        \Illuminate\Support\Facades\Storage::put($path, $pdfContent);

        $document = Document::create([
            'task_id' => $task->id,
            'case_id' => $task->case_id,
            'user_id' => $user->id,
            'filename' => $filename,
            'original_filename' => $validated['title'] . '.pdf',
            'mime_type' => 'application/pdf',
            'storage_path' => $path,
            'document_type' => 'ai_generated',
            'document_category' => $task->task_type,
            'processing_status' => 'completed',
            'extracted_text' => $validated['content'],
            'ai_summary' => $validated['title'],
            'encrypted' => false,
        ]);

        return response()->json([
            'success' => true,
            'document' => $document,
        ]);
    }

    private function buildDocumentHtml(string $title, string $content): string
    {
        $safeTitle   = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeContent = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
        $date        = now()->format('d/m/Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12pt; color: #1a1a1a; margin: 40px; }
  h1 { font-size: 15pt; color: #1a1a1a; margin: 0 0 6px; }
  .date { font-size: 9pt; color: #999; margin-bottom: 32px; }
  .content { font-size: 11pt; line-height: 1.75; white-space: pre-wrap; color: #2c2c2c; }
</style>
</head>
<body>
  <h1>{$safeTitle}</h1>
  <div class="date">Oprettet {$date}</div>
  <div class="content">{$safeContent}</div>
</body>
</html>
HTML;
    }
}
