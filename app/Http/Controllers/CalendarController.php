<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        $tasks = $user->tasks()
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->get(['id', 'title', 'due_date', 'priority', 'status', 'task_type']);

        $cases = CaseModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        return Inertia::render('Calendar', [
            'tasks' => $tasks,
            'cases' => $cases,
        ]);
    }
}
