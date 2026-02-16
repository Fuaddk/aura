<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Document;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $category = $request->query('category');

        $cases = CaseModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        // Count documents per category
        $allDocs = $user->documents()->get();
        $categoryCounts = [];
        foreach ($allDocs as $doc) {
            $cat = $doc->document_category ?: 'andet';
            $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
        }

        // Filter by category if selected
        $documents = [];
        if ($category) {
            $documents = $user->documents()
                ->where('document_category', $category)
                ->latest()
                ->get();
        }

        return Inertia::render('Documents', [
            'documents' => $documents,
            'cases' => $cases,
            'category' => $category,
            'categoryCounts' => $categoryCounts,
        ]);
    }

    public function download(Document $document)
    {
        $user = auth()->user();

        if ($document->user_id !== $user->id) {
            abort(403);
        }

        // AI-generated documents have content in extracted_text
        if ($document->document_type === 'ai_generated' && $document->extracted_text) {
            $filename = $document->original_filename ?: ($document->ai_summary . '.txt');
            return response($document->extracted_text, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        // For uploaded files
        if ($document->storage_path && $document->storage_path !== 'ai-generated' && file_exists(storage_path('app/' . $document->storage_path))) {
            return response()->download(
                storage_path('app/' . $document->storage_path),
                $document->original_filename
            );
        }

        abort(404);
    }
}
