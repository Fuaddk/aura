<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        // Count documents per category — uploadede filer tæller ikke med her
        $allDocs = $user->documents()->where('document_type', '!=', 'upload')->get();
        $categoryCounts = [];
        foreach ($allDocs as $doc) {
            $cat = $doc->document_category ?: 'andet';
            $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
        }

        // Filter by category if selected — uploadede filer udelukkes
        $documents = [];
        if ($category) {
            $documents = $user->documents()
                ->where('document_type', '!=', 'upload')
                ->when($category === 'andet', function ($q) {
                    // "Andet" inkluderer både document_category = 'andet' og NULL
                    $q->where(function ($q2) {
                        $q2->where('document_category', 'andet')
                           ->orWhereNull('document_category');
                    });
                }, function ($q) use ($category) {
                    $q->where('document_category', $category);
                })
                ->latest()
                ->get();
        }

        // Uploaded documents (via chat paperclip)
        $uploadedDocuments = $user->documents()
            ->where('document_type', 'upload')
            ->latest()
            ->get(['id', 'original_filename', 'filename', 'mime_type', 'file_size_bytes', 'processing_status', 'created_at', 'storage_path']);

        return Inertia::render('Documents', [
            'documents' => $documents,
            'cases' => $cases,
            'category' => $category,
            'categoryCounts' => $categoryCounts,
            'uploadedDocuments' => $uploadedDocuments,
        ]);
    }

    public function download(Document $document)
    {
        $user = auth()->user();

        if ($document->user_id !== $user->id) {
            abort(403);
        }

        // AI-generated documents: serve PDF from storage if available, else generate on the fly
        if ($document->document_type === 'ai_generated') {
            if ($document->storage_path && $document->storage_path !== 'ai-generated'
                && \Illuminate\Support\Facades\Storage::exists($document->storage_path)) {
                return response(\Illuminate\Support\Facades\Storage::get($document->storage_path), 200, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . ($document->original_filename ?: 'dokument.pdf') . '"',
                ]);
            }

            // Legacy: text-only record — generate PDF on the fly
            if ($document->extracted_text) {
                $title = $document->ai_summary ?: 'Dokument';
                $pdf   = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
                    $this->buildDocumentHtml($title, $document->extracted_text)
                );
                $pdf->setPaper('a4');
                $filename = \Str::slug($title) . '.pdf';
                return response($pdf->output(), 200, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            }
        }

        // For uploaded files
        if ($document->storage_path && $document->storage_path !== 'ai-generated'
            && file_exists(storage_path('app/' . $document->storage_path))) {
            return response()->download(
                storage_path('app/' . $document->storage_path),
                $document->original_filename
            );
        }

        abort(404);
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
