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
        $user = auth()->user();
        $category = $request->query('category');

        $cases = CaseModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        // Count tasks per category for the overview
        $allTasks = $user->tasks()->get();
        $categoryCounts = [];
        foreach ($allTasks as $task) {
            $type = $task->task_type ?: 'action';
            $categoryCounts[$type] = ($categoryCounts[$type] ?? 0) + 1;
        }

        // If a category is selected, filter tasks
        $tasks = [];
        if ($category) {
            $tasks = $user->tasks()
                ->where('task_type', $category)
                ->orderBy('status', 'asc')
                ->orderBy('priority', 'desc')
                ->orderBy('due_date', 'asc')
                ->get();
        }

        return Inertia::render('Tasks', [
            'tasks' => $tasks,
            'cases' => $cases,
            'category' => $category,
            'categoryCounts' => $categoryCounts,
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

        $document = Document::create([
            'task_id' => $task->id,
            'case_id' => $task->case_id,
            'user_id' => $user->id,
            'filename' => \Str::slug($validated['title']) . '.txt',
            'original_filename' => $validated['title'] . '.txt',
            'mime_type' => 'text/plain',
            'storage_path' => 'ai-generated',
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
}
