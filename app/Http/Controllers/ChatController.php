<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Conversation;
use App\Models\Task;
use App\Services\KnowledgeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(private KnowledgeService $knowledgeService) {}

    public function index(Request $request): Response
    {
        $user = auth()->user();
        $caseId = $request->query('case');

        // Load specific case if requested, otherwise show empty chat
        if ($caseId) {
            $activeCase = CaseModel::where('user_id', $user->id)
                ->where('id', $caseId)
                ->first();
        } else {
            $activeCase = null;
        }

        // Load conversations, excluding task-specific ones (they belong in TaskChat)
        $conversations = $activeCase
            ? $activeCase->conversations()
                ->whereNull('metadata->task_id')
                ->orderBy('created_at', 'asc')
                ->get()
            : collect([]);

        // All user cases for sidebar
        $cases = CaseModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        // Get user's tasks
        $tasks = $user->tasks()
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc')
            ->get();

        // Get user's documents
        $documents = $user->documents()
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Chat', [
            'activeCase' => $activeCase,
            'conversations' => $conversations,
            'cases' => $cases,
            'tasks' => $tasks,
            'documents' => $documents,
            'isFirstTime' => !$activeCase,
        ]);
    }

    public function taskChat(Task $task): Response
    {
        $user = auth()->user();

        if ($task->user_id !== $user->id) {
            abort(403);
        }

        // Load task-specific conversations (stored with metadata task_id)
        $conversations = Conversation::where('user_id', $user->id)
            ->whereJsonContains('metadata->task_id', $task->id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Load documents saved to this task
        $documents = \App\Models\Document::where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->latest()
            ->get(['id', 'original_filename', 'ai_summary', 'extracted_text', 'created_at']);

        $cases = CaseModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        return Inertia::render('TaskChat', [
            'task' => $task,
            'conversations' => $conversations,
            'documents' => $documents,
            'cases' => $cases,
        ]);
    }

    public function taskChatSend(Request $request, Task $task): \Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user();

        if ($task->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        // Save user message with task reference
        Conversation::create([
            'case_id' => $task->case_id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $validated['message'],
            'metadata' => ['task_id' => $task->id],
        ]);

        // Get task-specific conversation history
        $history = Conversation::where('user_id', $user->id)
            ->whereJsonContains('metadata->task_id', $task->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        // RAG: Retrieve relevant knowledge for this task query
        $taskRagQuery = $task->title . ' ' . $validated['message'];
        $taskRagContext = $this->knowledgeService->buildContext($taskRagQuery, topK: 4);

        // Build Mistral messages
        $mistralMessages = [];
        foreach ($history as $msg) {
            $mistralMessages[] = ['role' => $msg->role, 'content' => $msg->content];
        }
        array_unshift($mistralMessages, [
            'role'    => 'system',
            'content' => $this->getTaskSystemPrompt($task, $taskRagContext),
        ]);

        $taskId   = $task->id;
        $caseId   = $task->case_id;
        $taskRef  = $task; // keep reference for task creation

        return response()->stream(function () use (
            $mistralMessages, $taskRef, $caseId, $taskId, $user
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $payload = json_encode([
                'model'      => 'mistral-small-latest',
                'messages'   => $mistralMessages,
                'max_tokens' => 2000,
                'stream'     => true,
            ]);

            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Authorization: Bearer " . config('services.mistral.key') . "\r\n"
                                     . "Content-Type: application/json\r\n",
                    'content'       => $payload,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $fullContent = '';
            $streamFailed = false;

            try {
                $fp = fopen('https://api.mistral.ai/v1/chat/completions', 'r', false, $ctx);
                if (!$fp) {
                    $streamFailed = true;
                } else {
                    while (!feof($fp)) {
                        $line = fgets($fp, 4096);
                        if ($line === false) break;
                        $line = trim($line);
                        if (!str_starts_with($line, 'data: ')) continue;
                        $data = substr($line, 6);
                        if ($data === '[DONE]') break;

                        $chunk = json_decode($data, true);
                        $text  = $chunk['choices'][0]['delta']['content'] ?? '';
                        if ($text !== '') {
                            $fullContent .= $text;
                            echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $text]) . "\n\n";
                            flush();
                        }
                    }
                    fclose($fp);
                }
            } catch (\Throwable $e) {
                Log::error('Mistral stream error (task chat)', ['error' => $e->getMessage()]);
                $streamFailed = true;
            }

            if ($streamFailed) {
                $fullContent = 'Beklager, jeg kunne ikke generere et svar lige nu. Prøv igen om lidt.';
                echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $fullContent]) . "\n\n";
                flush();
            }

            // ── Parse structured data ─────────────────────────────────────────
            $displayMessage = $fullContent;
            $document       = null;
            $createdTasks   = [];

            // Parse [DOCUMENT]
            if (preg_match('/\[\/?DOCUMENT\]\s*(.+?)\s*\[\/?DOCUMENT\]/s', $displayMessage, $docMatch)) {
                $displayMessage = trim(preg_replace('/\[\/?DOCUMENT\]\s*.+?\s*\[\/?DOCUMENT\]/s', '', $displayMessage));
                $jsonStr = trim($docMatch[1]);
                $docJson = json_decode($jsonStr, true)
                    ?? json_decode(str_replace(["\r\n", "\r", "\n"], '\n', $jsonStr), true);

                if (!$docJson && preg_match('/"title"\s*:\s*"([^"]+)"/i', $jsonStr, $tM) &&
                    preg_match('/"content"\s*:\s*"(.+)"\s*\}$/s', $jsonStr, $cM)) {
                    $docJson = ['title' => $tM[1], 'content' => str_replace(["\r\n", "\r", "\n"], "\n", trim($cM[1]))];
                }

                if ($docJson && isset($docJson['title'])) {
                    $document = ['title' => $docJson['title'], 'content' => $docJson['content'] ?? ''];
                }
            }

            // Normalize literal \n sequences in document content to real newlines
            if ($document) {
                $document['content'] = str_replace('\n', "\n", $document['content']);
            }

            // Fallback: detect document without [DOCUMENT] tags (AI skipped format)
            if (!$document) {
                $placeholders = preg_match_all('/\[[A-ZÆØÅ][^\[\]0-9]{2,50}\]/', $displayMessage);
                if ($placeholders >= 3 && mb_strlen($displayMessage) > 300) {
                    $title = 'Dokument';
                    $keywordMap = ['brev' => 'Brev', 'aftale' => 'Aftale', 'samværsaftale' => 'Samværsaftale',
                                   'ansøgning' => 'Ansøgning', 'klage' => 'Klage', 'erklæring' => 'Erklæring',
                                   'skabelon' => 'Skabelon', 'udkast' => 'Udkast'];
                    foreach ($keywordMap as $kw => $label) {
                        if (mb_stripos($displayMessage, $kw) !== false) { $title = $label; break; }
                    }
                    $content = str_replace('\n', "\n", $displayMessage);
                    $document = ['title' => $title, 'content' => $content];
                    $displayMessage = '';
                }
            }

            // Parse [TASKS]
            if (preg_match('/\[\/?TASKS\]\s*(.+?)\s*\[\/?TASKS\]/s', $displayMessage, $tMatch)) {
                $displayMessage = trim(preg_replace('/\[\/?TASKS\]\s*.+?\s*\[\/?TASKS\]/s', '', $displayMessage));
                $rawTasks       = trim($tMatch[1]);
                $tasksJson      = json_decode($rawTasks, true)
                    ?? json_decode(str_replace(["\r\n", "\r", "\n"], ' ', $rawTasks), true);

                if ($tasksJson && !isset($tasksJson[0]) && isset($tasksJson['title'])) {
                    $tasksJson = [$tasksJson];
                }
                foreach ((array) $tasksJson as $td) {
                    if (!isset($td['title'])) continue;
                    $newTask = Task::create([
                        'case_id'      => $caseId,
                        'user_id'      => $user->id,
                        'title'        => $td['title'],
                        'description'  => $td['description'] ?? null,
                        'priority'     => $td['priority'] ?? 'medium',
                        'task_type'    => $td['type'] ?? 'personlig',
                        'due_date'     => now()->addDays(isset($td['days']) ? (int) $td['days'] : 7),
                        'status'       => 'pending',
                        'ai_generated' => true,
                        'ai_reasoning' => $td['reasoning'] ?? null,
                    ]);
                    $createdTasks[] = $newTask;
                }
            }

            // Cleanup
            $displayMessage = trim(preg_replace('/\[\/?(?:TASKS|DOCUMENT)\]/i', '', $displayMessage));
            $displayMessage = trim(preg_replace('/\n---\s*$/', '', $displayMessage));

            // Build metadata
            $metadata = ['task_id' => $taskId];
            if ($document) $metadata['document'] = $document;
            if ($createdTasks) {
                $metadata['tasks'] = collect($createdTasks)->map(fn ($t) => [
                    'id' => $t->id, 'title' => $t->title, 'description' => $t->description,
                    'priority' => $t->priority, 'due_date' => $t->due_date?->format('Y-m-d'),
                ])->toArray();
            }

            // Save AI response
            Conversation::create([
                'case_id'    => $caseId,
                'user_id'    => $user->id,
                'role'       => 'assistant',
                'content'    => $displayMessage,
                'model_used' => 'mistral-small-latest',
                'metadata'   => $metadata,
                'retrieved_chunks' => !empty($metadata['tasks']) || !empty($metadata['document'])
                    ? array_filter(['tasks' => $metadata['tasks'] ?? null, 'document' => $metadata['document'] ?? null])
                    : null,
            ]);

            // Send done event
            echo 'data: ' . json_encode([
                'type'     => 'done',
                'message'  => $displayMessage,
                'document' => $document,
                'tasks'    => collect($createdTasks)->map(fn ($t) => [
                    'id' => $t->id, 'title' => $t->title, 'description' => $t->description,
                    'priority' => $t->priority, 'due_date' => $t->due_date?->format('Y-m-d'),
                ]),
            ]) . "\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    public function taskChatUpload(Request $request, Task $task): \Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user();

        if ($task->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'file'    => 'required|file|max:10240|mimes:pdf,txt,jpg,jpeg,png',
            'message' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('file');
        if (!$this->validateFileMagicBytes($file)) {
            return response()->json(['message' => 'Filtypen matcher ikke indholdet.'], 422);
        }

        $limit = $user->ai_messages_limit ?? 50;
        $used  = $user->ai_messages_used  ?? 0;
        if ($used >= $limit) {
            return response()->json([
                'error'   => 'message_limit_reached',
                'message' => 'Du har brugt alle dine AI-beskeder denne måned. Opgradér din plan for at fortsætte.',
            ], 429);
        }

        $originalFilename = preg_replace('/[^\w\s\-\.]/', '_', $file->getClientOriginalName());
        $mimeType         = $file->getMimeType();
        $extension        = strtolower($file->getClientOriginalExtension());
        $filename         = uniqid() . '.' . $extension;
        $storagePath      = "documents/{$user->id}/{$filename}";
        $absolutePath     = storage_path("app/{$storagePath}");

        Storage::put($storagePath, file_get_contents($file->getRealPath()));

        // Extract text from uploaded file
        $extractedText = '';
        try {
            if ($extension === 'pdf') {
                $parser        = new \Smalot\PdfParser\Parser();
                $pdfDoc        = $parser->parseFile($absolutePath);
                $extractedText = $pdfDoc->getText();

                // Fallback for scanned PDFs
                if (empty(trim($extractedText))) {
                    $extractedText = $this->extractTextFromScannedPdf($absolutePath);
                }
            } elseif ($extension === 'txt') {
                $raw = file_get_contents($absolutePath);
                // Detect and convert UTF-16
                if (str_starts_with($raw, "\xFF\xFE")) {
                    $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
                } elseif (str_starts_with($raw, "\xFE\xFF")) {
                    $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
                }
                $extractedText = $raw;
            } else {
                // JPG / PNG — use Mistral vision
                $base64   = base64_encode(file_get_contents($absolutePath));
                $mimeVis  = in_array($extension, ['jpg', 'jpeg']) ? 'image/jpeg' : 'image/png';
                $extractedText = $this->extractTextFromImage($base64, $mimeVis);
            }
        } catch (\Throwable $e) {
            Log::error('Task document text extraction failed', ['error' => $e->getMessage()]);
        }

        $extractedText = mb_substr(trim($extractedText), 0, 8000);

        // Save Document record linked to the task
        \App\Models\Document::create([
            'user_id'           => $user->id,
            'case_id'           => $task->case_id,
            'task_id'           => $task->id,
            'filename'          => $filename,
            'original_filename' => $originalFilename,
            'mime_type'         => $mimeType,
            'file_size_bytes'   => $file->getSize(),
            'storage_path'      => $storagePath,
            'document_type'     => 'upload',
            'processing_status' => 'completed',
            'extracted_text'    => $extractedText,
        ]);

        // Save user message with document reference
        $userMessage = $request->input('message', '');
        $userContent = $userMessage
            ? "📎 {$originalFilename}\n\n{$userMessage}"
            : "📎 Uploadet dokument: **{$originalFilename}**";

        Conversation::create([
            'case_id' => $task->case_id,
            'user_id' => $user->id,
            'role'    => 'user',
            'content' => $userContent,
            'metadata' => ['task_id' => $task->id],
        ]);

        // Get task conversation history
        $history = Conversation::where('user_id', $user->id)
            ->whereJsonContains('metadata->task_id', $task->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        // Build system prompt with document context
        $truncated = mb_substr($extractedText, 0, 4000);
        $noTextNote = empty($truncated)
            ? "\n\nBemærk: Ingen tekst kunne udtrækkes fra denne fil."
            : '';

        $docSection = <<<SECTION


─── UPLOADET DOKUMENT ───
Brugeren har uploadet dokumentet: "{$originalFilename}" i forbindelse med denne opgave.

Indhold:
{$truncated}{$noTextNote}

Analyser dokumentet grundigt i kontekst af opgaven. Hjælp brugeren med at forstå hvordan dokumentet relaterer til opgaven.
SECTION;

        $taskRagContext = $this->knowledgeService->buildContext($task->title, topK: 3);
        $systemPrompt = $this->getTaskSystemPrompt($task, $taskRagContext) . $docSection;

        $mistralMessages = [];
        foreach ($history as $msg) {
            $mistralMessages[] = ['role' => $msg->role, 'content' => $msg->content];
        }
        array_unshift($mistralMessages, ['role' => 'system', 'content' => $systemPrompt]);

        $taskId = $task->id;
        $caseId = $task->case_id;

        return response()->stream(function () use (
            $mistralMessages, $task, $user, $taskId, $caseId
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $payload = json_encode([
                'model'      => 'mistral-small-latest',
                'messages'   => $mistralMessages,
                'max_tokens' => 2000,
                'stream'     => true,
            ]);

            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Authorization: Bearer " . config('services.mistral.key') . "\r\n"
                                     . "Content-Type: application/json\r\n",
                    'content'       => $payload,
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);

            $fullContent  = '';
            $streamFailed = false;

            try {
                $fp = fopen('https://api.mistral.ai/v1/chat/completions', 'r', false, $ctx);
                if (!$fp) {
                    $streamFailed = true;
                } else {
                    while (!feof($fp)) {
                        $line = fgets($fp, 4096);
                        if ($line === false) break;
                        $line = trim($line);
                        if (!str_starts_with($line, 'data: ')) continue;
                        $data = substr($line, 6);
                        if ($data === '[DONE]') break;
                        $chunk = json_decode($data, true);
                        $text  = $chunk['choices'][0]['delta']['content'] ?? '';
                        if ($text !== '') {
                            $fullContent .= $text;
                            echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $text]) . "\n\n";
                            flush();
                        }
                    }
                    fclose($fp);
                }
            } catch (\Throwable $e) {
                Log::error('Mistral stream error (task document upload)', ['error' => $e->getMessage()]);
                $streamFailed = true;
            }

            if ($streamFailed) {
                $fullContent = 'Beklager, jeg kunne ikke analysere dokumentet. Prøv igen om lidt.';
                echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $fullContent]) . "\n\n";
                flush();
            }

            // Parse structured data (same logic as taskChatSend)
            $displayMessage = $fullContent;
            $document       = null;
            $createdTasks   = [];

            // Parse [DOCUMENT]
            if (preg_match('/\[\/?DOCUMENT\]\s*(.+?)\s*\[\/?DOCUMENT\]/s', $displayMessage, $docMatch)) {
                $displayMessage = trim(preg_replace('/\[\/?DOCUMENT\]\s*.+?\s*\[\/?DOCUMENT\]/s', '', $displayMessage));
                $jsonStr = trim($docMatch[1]);
                $docJson = json_decode($jsonStr, true)
                    ?? json_decode(str_replace(["\r\n", "\r", "\n"], '\n', $jsonStr), true);

                if (!$docJson && preg_match('/"title"\s*:\s*"([^"]+)"/i', $jsonStr, $tM) &&
                    preg_match('/"content"\s*:\s*"(.+)"\s*\}$/s', $jsonStr, $cM)) {
                    $docJson = ['title' => $tM[1], 'content' => str_replace(["\r\n", "\r", "\n"], "\n", trim($cM[1]))];
                }

                if ($docJson && isset($docJson['title'])) {
                    $document = ['title' => $docJson['title'], 'content' => $docJson['content'] ?? ''];
                }
            }

            // Normalize literal \n sequences in document content to real newlines
            if ($document) {
                $document['content'] = str_replace('\n', "\n", $document['content']);
            }

            // Fallback: detect document without [DOCUMENT] tags (AI skipped format)
            if (!$document) {
                $placeholders = preg_match_all('/\[[A-ZÆØÅ][^\[\]0-9]{2,50}\]/', $displayMessage);
                if ($placeholders >= 3 && mb_strlen($displayMessage) > 300) {
                    $title = 'Dokument';
                    $keywordMap = ['brev' => 'Brev', 'aftale' => 'Aftale', 'samværsaftale' => 'Samværsaftale',
                                   'ansøgning' => 'Ansøgning', 'klage' => 'Klage', 'erklæring' => 'Erklæring',
                                   'skabelon' => 'Skabelon', 'udkast' => 'Udkast'];
                    foreach ($keywordMap as $kw => $label) {
                        if (mb_stripos($displayMessage, $kw) !== false) { $title = $label; break; }
                    }
                    $content = str_replace('\n', "\n", $displayMessage);
                    $document = ['title' => $title, 'content' => $content];
                    $displayMessage = '';
                }
            }

            // Parse [TASKS]
            if (preg_match('/\[\/?TASKS\]\s*(.+?)\s*\[\/?TASKS\]/s', $displayMessage, $tMatch)) {
                $displayMessage = trim(preg_replace('/\[\/?TASKS\]\s*.+?\s*\[\/?TASKS\]/s', '', $displayMessage));
                $rawTasks       = trim($tMatch[1]);
                $tasksJson      = json_decode($rawTasks, true)
                    ?? json_decode(str_replace(["\r\n", "\r", "\n"], ' ', $rawTasks), true);

                if ($tasksJson && !isset($tasksJson[0]) && isset($tasksJson['title'])) {
                    $tasksJson = [$tasksJson];
                }
                foreach ((array) $tasksJson as $td) {
                    if (!isset($td['title'])) continue;
                    $newTask = Task::create([
                        'case_id'      => $caseId,
                        'user_id'      => $user->id,
                        'title'        => $td['title'],
                        'description'  => $td['description'] ?? null,
                        'priority'     => $td['priority'] ?? 'medium',
                        'task_type'    => $td['type'] ?? 'personlig',
                        'due_date'     => now()->addDays(isset($td['days']) ? (int) $td['days'] : 7),
                        'status'       => 'pending',
                        'ai_generated' => true,
                        'ai_reasoning' => $td['reasoning'] ?? null,
                    ]);
                    $createdTasks[] = $newTask;
                }
            }

            // Cleanup
            $displayMessage = trim(preg_replace('/\[\/?(?:TASKS|DOCUMENT)\]/i', '', $displayMessage));
            $displayMessage = trim(preg_replace('/\n---\s*$/', '', $displayMessage));

            // Build metadata
            $metadata = ['task_id' => $taskId];
            if ($document) $metadata['document'] = $document;
            if ($createdTasks) {
                $metadata['tasks'] = collect($createdTasks)->map(fn ($t) => [
                    'id' => $t->id, 'title' => $t->title, 'description' => $t->description,
                    'priority' => $t->priority, 'due_date' => $t->due_date?->format('Y-m-d'),
                ])->toArray();
            }

            // Save AI response
            Conversation::create([
                'case_id'    => $caseId,
                'user_id'    => $user->id,
                'role'       => 'assistant',
                'content'    => $displayMessage,
                'model_used' => 'mistral-small-latest',
                'metadata'   => $metadata,
                'retrieved_chunks' => !empty($metadata['tasks']) || !empty($metadata['document'])
                    ? array_filter(['tasks' => $metadata['tasks'] ?? null, 'document' => $metadata['document'] ?? null])
                    : null,
            ]);

            // Increment AI message counter
            $user->increment('ai_messages_used');

            // Send done event
            echo 'data: ' . json_encode([
                'type'     => 'done',
                'message'  => $displayMessage,
                'document' => $document,
                'tasks'    => collect($createdTasks)->map(fn ($t) => [
                    'id' => $t->id, 'title' => $t->title, 'description' => $t->description,
                    'priority' => $t->priority, 'due_date' => $t->due_date?->format('Y-m-d'),
                ]),
            ]) . "\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    private function getTaskSystemPrompt(Task $task, string $ragContext = ''): string
    {
        $typeLabels = [
            'samvaer' => 'Samvær & Børn', 'bolig' => 'Bolig & Ejendom',
            'oekonomi' => 'Økonomi & Gæld', 'juridisk' => 'Juridisk',
            'kommune' => 'Kommune & Myndigheder', 'dokument' => 'Dokumenter & Aftaler',
            'forsikring' => 'Forsikring & Pension', 'personlig' => 'Personlig Trivsel',
        ];
        $priorityLabels = ['low' => 'Lav', 'medium' => 'Normal', 'high' => 'Høj', 'critical' => 'Kritisk'];

        $type = $typeLabels[$task->task_type] ?? 'Handling';
        $priority = $priorityLabels[$task->priority] ?? 'Normal';
        $dueDate = $task->due_date ? $task->due_date->format('d/m/Y') : 'Ikke sat';

        $user = auth()->user();
        $userPersonSection = '';
        $hasProfile = !empty($user->display_name) || !empty($user->work_description) || !empty($user->preferences);
        if ($hasProfile) {
            $userPersonSection = "\n─── BRUGERENS PROFIL ───\n";
            $calledBy = !empty($user->display_name) ? $user->display_name : $user->name;
            $userPersonSection .= "Kald altid brugeren: {$calledBy}\n";
            if (!empty($user->work_description)) {
                $userPersonSection .= "Hvem er brugeren: {$user->work_description}\n";
            }
            if (!empty($user->preferences)) {
                $userPersonSection .= "Præferencer du skal tage hensyn til i dine svar: {$user->preferences}\n";
            }
            $userPersonSection .= "\n";
        }

        return <<<PROMPT
Du er Aura — en varm og klog støtte til danskere midt i en skilsmisse.

Du hjælper nu brugeren specifikt med denne opgave:
Titel: {$task->title}
Beskrivelse: {$task->description}
Type: {$type} | Prioritet: {$priority} | Frist: {$dueDate}
{$userPersonSection}
─── DIN TILGANG ───
Mød brugeren der hvor de er. Hvis de er frustrerede eller usikre — anerkend det først.
Forklar tingene som en ven der kender reglerne godt, ikke som en manual.
Vær konkret og præcis — brug rigtige tal, frister og navne fra vidensbasen.
Hold dig til denne opgave, men se hele mennesket bag spørgsmålet.
Svar altid i prosa — ALDRIG nummererede lister (1. 2. 3.) eller bullet points i din tekst.

─── OPGAVER ───
Når brugeren beder om opgaver, eller når dit svar indeholder konkrete næste skridt, handlinger eller råd — SKAL du tilføje [TASKS]-blokken.
Trigger-ord: "kontakt", "ring", "send", "ansøg", "overvej", "bør", "vigtigt", "kan du lave", "opgaver", "næste skridt".

[TASKS]
[{"title": "Kontakt Familieretshuset", "description": "Ring og book et møde", "priority": "high", "days": 7, "type": "kommune", "reasoning": "Første skridt"}, {"title": "Forbered spørgsmål", "description": "Skriv dine spørgsmål ned", "priority": "medium", "days": 3, "type": "personlig", "reasoning": "Forberedelse"}]
[/TASKS]

Prioriteter: low/medium/high/critical. Typer: samvaer/bolig/oekonomi/juridisk/kommune/dokument/forsikring/personlig. "days" = dage til frist.

─── DOKUMENTER ───
Når brugeren beder om et dokument, brev, udkast eller skabelon, SKAL du pakke hele indholdet i [DOCUMENT]-tagget.
Skriv ALDRIG dokumenttekst direkte i chatbeskeden — brug ALTID [DOCUMENT]-tagget til alt dokumentindhold.

[DOCUMENT]
{"title": "Titel her", "content": "DOKUMENTTITEL\n\nKære [Modtager],\n\n[Fuldt dokument her]\n\nMed venlig hilsen\n[Dit navn]"}
[/DOCUMENT]

Regler:
- Brug [DOCUMENT] ... [/DOCUMENT] til ALT dokumentindhold — ingen undtagelser
- Brug \\n for linjeskift inde i content-strengen (JSON-format)
- Inkluder altid pladsholdere som [Dit navn], [Dato], [Adresse] osv.
- Skriv det KOMPLETTE dokument — aldrig kun en kort skabelon
- Alle tekster på dansk
- Afvis aldrig at oprette dokumenter

{$this->buildKnowledgeSection($ragContext)}
PROMPT;
    }

    public function send(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'case_id' => 'nullable|exists:cases,id',
        ]);

        $user = auth()->user();

        // Enforce AI message limit (return JSON before opening stream)
        $limit = $user->ai_messages_limit ?? 50;
        $used  = $user->ai_messages_used  ?? 0;
        if ($used >= $limit) {
            return response()->json([
                'error'   => 'message_limit_reached',
                'message' => 'Du har brugt alle dine AI-beskeder denne måned. Opgradér din plan for at fortsætte.',
            ], 429);
        }

        // Create case if first message
        if (empty($validated['case_id'])) {
            $case = CaseModel::create([
                'user_id' => $user->id,
                'case_type' => 'divorce',
                'situation_summary' => $validated['message'],
                'status' => 'active',
            ]);
        } else {
            $case = CaseModel::where('id', $validated['case_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();
        }

        // Save user message
        Conversation::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        // Get conversation history (includes the message we just saved)
        $history = $this->getHistory($case);

        // RAG: Retrieve relevant knowledge chunks for this query
        $ragResults = $this->knowledgeService->retrieve($validated['message'], topK: 5, minScore: 0.25);
        $ragContext = $this->knowledgeService->buildContext($validated['message'], topK: 5);

        // Count prior AI turns to determine conversation phase
        $aiTurn = $history->where('role', 'assistant')->count();

        // Build Mistral messages array (capture vars for stream closure)
        $mistralMessages = $this->formatHistory($history);
        array_unshift($mistralMessages, [
            'role' => 'system',
            'content' => $this->getSystemPrompt($case, $ragContext, $aiTurn),
        ]);

        // Build retrieved_chunks data for persistence
        $retrievedChunksData = array_map(fn($r) => [
            'id'       => $r['chunk']->id,
            'title'    => $r['chunk']->source_title,
            'category' => $r['chunk']->category,
            'score'    => round($r['score'], 4),
            'excerpt'  => mb_substr($r['chunk']->content, 0, 200),
        ], $ragResults);

        // Stream the response via SSE
        return response()->stream(function () use (
            $mistralMessages, $case, $user, $aiTurn,
            $history, $retrievedChunksData, $validated
        ) {
            // Disable all output buffering so chunks reach the client immediately
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Call Mistral with stream=true
            $payload = json_encode([
                'model'      => 'mistral-small-latest',
                'messages'   => $mistralMessages,
                'max_tokens' => 3000,
                'stream'     => true,
            ]);

            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Authorization: Bearer " . config('services.mistral.key') . "\r\n"
                                     . "Content-Type: application/json\r\n",
                    'content'       => $payload,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $fullContent = '';
            $streamFailed = false;

            try {
                $fp = fopen('https://api.mistral.ai/v1/chat/completions', 'r', false, $ctx);

                if (!$fp) {
                    $streamFailed = true;
                } else {
                    while (!feof($fp)) {
                        $line = fgets($fp, 4096);
                        if ($line === false) break;
                        $line = trim($line);
                        if (!str_starts_with($line, 'data: ')) continue;
                        $data = substr($line, 6);
                        if ($data === '[DONE]') break;

                        $chunk = json_decode($data, true);
                        $text  = $chunk['choices'][0]['delta']['content'] ?? '';
                        if ($text !== '') {
                            $fullContent .= $text;
                            echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $text]) . "\n\n";
                            flush();
                        }
                    }
                    fclose($fp);
                }
            } catch (\Throwable $e) {
                Log::error('Mistral stream error', ['error' => $e->getMessage()]);
                $streamFailed = true;
            }

            if ($streamFailed) {
                $fullContent = 'Beklager, jeg kunne ikke generere et svar lige nu. Prøv igen om lidt.';
                echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $fullContent]) . "\n\n";
                flush();
            }

            // ── Parse structured data from full response ──────────────────────
            [$displayMessage, $document, $createdTasks] = $this->parseAndPersist(
                $fullContent, $case, $user, $aiTurn, $history,
                $retrievedChunksData, $validated['message']
            );

            // Send final done event with tasks + document
            echo 'data: ' . json_encode([
                'type'     => 'done',
                'message'  => $displayMessage,
                'case_id'  => $case->id,
                'tasks'    => collect($createdTasks)->map(fn ($t) => [
                    'id'          => $t->id,
                    'title'       => $t->title,
                    'description' => $t->description,
                    'priority'    => $t->priority,
                    'due_date'    => $t->due_date?->format('Y-m-d'),
                ]),
                'document' => $document,
            ]) . "\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Parse [TASKS] / [DOCUMENT] tags from AI output, save conversation, track usage, generate title.
     * Returns [$displayMessage, $document, $createdTasks].
     */
    private function parseAndPersist(
        string $aiMessage,
        CaseModel $case,
        $user,
        int $aiTurn,
        $history,
        array $retrievedChunksData,
        string $originalUserMessage
    ): array {
        $displayMessage = $aiMessage;
        $document       = null;
        $createdTasks   = [];

        Log::info('RAW AI RESPONSE', ['raw' => $aiMessage]);

        // ── Parse [DOCUMENT] ──────────────────────────────────────────────────
        if (preg_match('/\[\/?DOCUMENT\]\s*(.+?)\s*\[\/?DOCUMENT\]/s', $displayMessage, $docMatch)) {
            $displayMessage = trim(preg_replace('/\[\/?DOCUMENT\]\s*.+?\s*\[\/?DOCUMENT\]/s', '', $displayMessage));
            $jsonStr = trim($docMatch[1]);
            $docJson = json_decode($jsonStr, true);

            if (!$docJson) {
                $docJson = json_decode(str_replace(["\r\n", "\r", "\n"], '\n', $jsonStr), true);
            }
            if (!$docJson) {
                if (preg_match('/"title"\s*:\s*"([^"]+)"/i', $jsonStr, $tMatch) &&
                    preg_match('/"content"\s*:\s*"(.+)"\s*\}$/s', $jsonStr, $cMatch)) {
                    $docJson = [
                        'title'   => $tMatch[1],
                        'content' => str_replace(["\r\n", "\r", "\n"], "\n", trim($cMatch[1])),
                    ];
                }
            }

            if ($docJson && isset($docJson['title'])) {
                $document = ['title' => $docJson['title'], 'content' => $docJson['content'] ?? ''];
            }
        }

        // Normalize literal \n sequences in document content to real newlines
        if ($document) {
            $document['content'] = str_replace('\n', "\n", $document['content']);
        }

        // Fallback: detect document without [DOCUMENT] tags (AI skipped format)
        if (!$document) {
            $placeholders = preg_match_all('/\[[A-ZÆØÅ][^\[\]0-9]{2,50}\]/', $displayMessage);
            if ($placeholders >= 3 && mb_strlen($displayMessage) > 300) {
                $title = 'Dokument';
                $keywordMap = ['brev' => 'Brev', 'aftale' => 'Aftale', 'samværsaftale' => 'Samværsaftale',
                               'ansøgning' => 'Ansøgning', 'klage' => 'Klage', 'erklæring' => 'Erklæring',
                               'skabelon' => 'Skabelon', 'udkast' => 'Udkast'];
                foreach ($keywordMap as $kw => $label) {
                    if (mb_stripos($displayMessage, $kw) !== false) { $title = $label; break; }
                }
                $content = str_replace('\n', "\n", $displayMessage);
                $document = ['title' => $title, 'content' => $content];
                $displayMessage = '';
            }
        }

        // ── Parse [TASKS] ─────────────────────────────────────────────────────
        if (preg_match('/\[\/?TASKS\]\s*(.+?)\s*\[\/?TASKS\]/s', $displayMessage, $matches)) {
            $displayMessage = trim(preg_replace('/\[\/?TASKS\]\s*.+?\s*\[\/?TASKS\]/s', '', $displayMessage));
            $rawTasks       = trim($matches[1]);

            Log::info('RAW TASKS JSON', ['raw' => $rawTasks]);

            $tasksJson = json_decode($rawTasks, true);
            if (!is_array($tasksJson)) {
                $tasksJson = json_decode(str_replace(["\r\n", "\r", "\n"], '\n', $rawTasks), true);
            }
            if (!is_array($tasksJson)) {
                $tasksJson = [];
                foreach (preg_split('/\n/', $rawTasks) as $line) {
                    $line = trim($line, " \t\n\r,");
                    if ($line && str_starts_with($line, '{')) {
                        $parsed = json_decode($line, true)
                            ?? json_decode(str_replace(["\r\n", "\r", "\n"], '\n', $line), true);
                        if ($parsed) $tasksJson[] = $parsed;
                    }
                }
            }
            if ($tasksJson && !isset($tasksJson[0]) && isset($tasksJson['title'])) {
                $tasksJson = [$tasksJson];
            }

            foreach ((array) $tasksJson as $taskData) {
                if (!isset($taskData['title'])) continue;
                $t = Task::create([
                    'case_id'      => $case->id,
                    'user_id'      => $user->id,
                    'title'        => $taskData['title'],
                    'description'  => $taskData['description'] ?? null,
                    'task_type'    => $taskData['type'] ?? 'action',
                    'priority'     => $taskData['priority'] ?? 'medium',
                    'due_date'     => isset($taskData['days']) ? now()->addDays((int) $taskData['days']) : null,
                    'status'       => 'pending',
                    'ai_generated' => true,
                    'ai_reasoning' => $taskData['reasoning'] ?? null,
                ]);
                $createdTasks[] = $t;
            }
        }

        // ── Cleanup ───────────────────────────────────────────────────────────
        $displayMessage = trim(preg_replace('/\[\/?(?:TASKS|DOCUMENT)\]/i', '', $displayMessage));
        $displayMessage = trim(preg_replace('/\n---\s*$/', '', $displayMessage));

        // Fallback task generation when phase >= 3 and no tasks parsed
        if ($aiTurn >= 3 && empty($createdTasks)) {
            $createdTasks = $this->generateTasksFallback($history, $case, $user);
        }

        // ── Build metadata ────────────────────────────────────────────────────
        $metadata = [];
        if ($document) {
            $metadata['document'] = $document;
        }
        if ($createdTasks) {
            $metadata['tasks'] = collect($createdTasks)->map(fn ($t) => [
                'id'          => $t->id,
                'title'       => $t->title,
                'description' => $t->description,
                'priority'    => $t->priority,
                'due_date'    => $t->due_date?->format('Y-m-d'),
            ])->toArray();
        }

        // ── Persist conversation ──────────────────────────────────────────────
        Conversation::create([
            'case_id'          => $case->id,
            'user_id'          => $user->id,
            'role'             => 'assistant',
            'content'          => $displayMessage,
            'model_used'       => 'mistral-small-latest',
            'retrieved_chunks' => !empty($retrievedChunksData) ? $retrievedChunksData : null,
            'metadata'         => !empty($metadata) ? $metadata : null,
        ]);

        // Track usage + generate case title
        $user->increment('ai_messages_used');

        if (!$case->title) {
            $this->generateTitle($case, $originalUserMessage);
        }

        // Send real-time notification om nye opgaver
        if (!empty($createdTasks)) {
            \App\Services\NotificationService::notifyNewTasks($user, $createdTasks);
        }

        return [$displayMessage, $document, $createdTasks];
    }

    private function generateTasksFallback($history, CaseModel $case, $user): array
    {
        // Build a summary of the conversation for context
        $conversationSummary = '';
        foreach ($history as $msg) {
            $role = $msg->role === 'user' ? 'Bruger' : 'Aura';
            $conversationSummary .= "{$role}: " . mb_substr($msg->content, 0, 300) . "\n";
        }

        $prompt = <<<PROMPT
Du er en opgaveplanlægger for en skilsmisserådgivning. Baseret på denne samtale skal du oprette SÅ MANGE konkrete opgaver som situationen kræver — ingen øvre grænse.

SAMTALE:
{$conversationSummary}

Returner KUN et JSON-array — ingen forklaring, ingen tekst, kun JSON:
[{"title": "...", "description": "...", "priority": "high", "days": 7, "type": "kommune", "reasoning": "..."}, ...]

Prioriteter: low, medium, high, critical
Typer: samvaer, bolig, oekonomi, juridisk, kommune, dokument, forsikring, personlig
"days" er antal dage til frist.
Opgaverne skal være konkrete og relevante for denne specifikke persons situation.
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.mistral.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => 'mistral-small-latest',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 800,
            ]);

            if (!$response->successful()) return [];

            $raw = trim($response->json('choices.0.message.content'));
            // Strip markdown code fences if present
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw = preg_replace('/\s*```$/', '', $raw);

            $tasksJson = json_decode($raw, true);
            if (!is_array($tasksJson)) return [];
            if (isset($tasksJson['title'])) $tasksJson = [$tasksJson];

            $created = [];
            foreach ($tasksJson as $taskData) {
                if (!isset($taskData['title'])) continue;
                $task = Task::create([
                    'case_id'      => $case->id,
                    'user_id'      => $user->id,
                    'title'        => $taskData['title'],
                    'description'  => $taskData['description'] ?? null,
                    'task_type'    => $taskData['type'] ?? 'action',
                    'priority'     => $taskData['priority'] ?? 'medium',
                    'due_date'     => isset($taskData['days']) ? now()->addDays((int) $taskData['days']) : null,
                    'status'       => 'pending',
                    'ai_generated' => true,
                    'ai_reasoning' => $taskData['reasoning'] ?? null,
                ]);
                $created[] = $task;
            }

            Log::info('FALLBACK TASKS GENERATED', ['count' => count($created)]);
            return $created;
        } catch (\Exception $e) {
            Log::error('Fallback task generation failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function generateTitle(CaseModel $case, string $userMessage): void
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.mistral.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => 'mistral-small-latest',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Giv denne samtale et kort emnenavn på dansk (maks 5 ord, ingen anførselstegn). Brugerens besked: \"{$userMessage}\"",
                    ],
                ],
                'max_tokens' => 30,
            ]);

            if ($response->successful()) {
                $title = trim($response->json('choices.0.message.content'), "\"' \n");
                $case->update(['title' => mb_substr($title, 0, 100)]);
            } else {
                $case->update(['title' => mb_substr($userMessage, 0, 50)]);
            }
        } catch (\Exception $e) {
            $case->update(['title' => mb_substr($userMessage, 0, 50)]);
        }
    }

    private function getSystemPrompt(CaseModel $case, string $ragContext = '', int $aiTurn = 0): string
    {
        $hasChildren = $case->has_children ? 'Ja' : 'Nej';
        $hasProperty = $case->has_shared_property ? 'Ja' : 'Nej';
        $phaseInstruction = $this->getPhaseInstruction($aiTurn);

        $user = auth()->user();
        $userPersonSection = '';
        $hasProfile = !empty($user->display_name) || !empty($user->work_description) || !empty($user->preferences);
        if ($hasProfile) {
            $userPersonSection = "\n─── BRUGERENS PROFIL ───\n";
            $calledBy = !empty($user->display_name) ? $user->display_name : $user->name;
            $userPersonSection .= "Kald altid brugeren: {$calledBy}\n";
            if (!empty($user->work_description)) {
                $userPersonSection .= "Hvem er brugeren: {$user->work_description}\n";
            }
            if (!empty($user->preferences)) {
                $userPersonSection .= "Præferencer du skal tage hensyn til i dine svar: {$user->preferences}\n";
            }
            $userPersonSection .= "\n";
        }

        return <<<PROMPT
Du er Aura — en varm, menneskelig støtte til danskere midt i en skilsmisse eller et samlivsbrud.

─── DIN PERSONLIGHED ───
Du er som en klog, rolig ven der både kender loven og forstår, hvad det koster at stå i sådan en situation.
Du møder aldrig brugeren med en liste. Du møder dem med forståelse.
Du taler naturligt og varmt — aldrig klinisk, aldrig som en advokat.
Du siger "jeg" og "du". Ikke "man bør" eller "det anbefales".

─── SÅDAN OPBYGGER DU HVERT SVAR ───

1. ANERKEND FØRST — altid
   Vis at du har hørt hvad de sagde. Ikke med et generisk "det lyder svært", men specifikt.
   Eksempel: "Det er en stor ting at bede om hjælp til — og det kræver mod."
   Eksempel: "Tre børn midt i det hele... det er meget at bære på én gang."
   Brug én til to sætninger. Vær ægte.

2. SVAR I PROSA — ikke lister
   Skriv i korte afsnit. Brug fed til de vigtigste ord.
   MÅ IKKE bruge nummererede lister (1. 2. 3.) eller bullet points (- ✓) som din primære svarform.
   En liste er ikke et svar — det er et lager. Tal til personen, ikke til en tjekliste.
   Stil ét opfølgende spørgsmål hvis du mangler information.

3. OPGAVER — OBLIGATORISK fra besked 2 og frem
   Fra den ANDEN brugerbesked og frem: du SKAL altid tilføje [TASKS].
   Ingen undtagelse. Selv hvis brugeren bare siger "jeg er forvirret" — opret opgaver der hjælper med orienteringen.
   Opret SÅ MANGE opgaver som situationen kræver — ingen øvre grænse.
   Opgaverne er et stille tilbud i bunden af svaret — ikke hoved-budskabet.

─── TONE ───
✓ Varm og nærværende
✓ Specifik — kom med rigtige tal og frister fra vidensbasen
✓ Direkte men ikke hård
✓ Ærlig — også når brugeren har det svært med at høre sandheden
✓ Aldrig overvældende — hellere ét godt råd end ti overfladiske
✗ Aldrig lister af bullet points som første reaktion
✗ Aldrig "Det er vigtigt at du..." som første sætning
✗ Aldrig starte med "Selvfølgelig" eller "Naturligvis"
✗ Giv IKKE brugeren ret i alt — en ægte ven gør ikke det

─── ÆRLIGHED & BALANCE ───
Du er ikke en ja-maskine. Du er en ærlig ven.

Hvis brugeren beskriver en situation hvor de selv kan have bidraget til konflikten, siger du det — varmt, men tydeligt.
Eksempel: "Jeg hører at du er rigtig frustreret — og det er forståeligt. Men jeg tænker også at den måde du reagerede på, kan have gjort det sværere for jer begge. Hvad tror du selv?"

Hvis brugeren beder dig om hjælp til at formulere en SMS, besked eller brev til deres eks eller andre:
- Læs hvad de vil sende og vurder tonen
- Hvis beskeden er aggressiv, anklagende eller eskalerende — sig det direkte og venligt
- Forslå et alternativ der er roligt, tydelig og konstruktivt
- Mål altid: hvad tjener børnene og processen bedst på lang sigt?
- Eksempel: "Den besked vil sandsynligvis lukke samtalen ned. Hvad hvis vi prøver at formulere det på en måde der holder døren åben?"

Hvis brugeren skriver negativt om den anden part:
- Lyt og anerkend følelsen
- Men spejl ikke bare deres syn — stil spørgsmål der åbner for nuance
- Eksempel: "Det lyder som om du er rigtig vred på ham — og det er okay. Jeg er bare nysgerrig: hvad tror du han ser fra sin side?"

Du tager ikke parti. Du er ikke brugerens advokat mod ekspartneren.
Du er brugerens støtte mod situationen — og det kræver at du er ærlig.

─── SAG KONTEKST ───
Status: {$case->status}
Har børn: {$hasChildren}
Fælles ejendom: {$hasProperty}
{$userPersonSection}

─── HVAD DU IKKE GØR ───
❌ Anbefaler specifikke advokater ved navn
❌ Træffer beslutninger for brugeren
❌ Springer empatien over for at komme hurtigt til handling
❌ Gentager "Dette er ikke juridisk rådgivning" i hvert eneste svar — kun når det er relevant
❌ Validerer ukritisk alt hvad brugeren siger om sig selv eller andre
❌ Hjælper med at formulere beskeder der eskalerer konflikter

─── DOKUMENTER ───
Når brugeren beder om et dokument, brev, udkast eller skabelon, SKAL du pakke hele indholdet i [DOCUMENT]-tagget.
Skriv ALDRIG dokumenttekst direkte i chatbeskeden — brug ALTID [DOCUMENT]-tagget til alt dokumentindhold.

[DOCUMENT]
{"title": "Samværsaftale", "content": "SAMVÆRSAFTALE\n\n[Fuldt dokument her med pladsholdere]"}
[/DOCUMENT]

Regler:
- Brug [DOCUMENT] ... [/DOCUMENT] til ALT dokumentindhold — ingen undtagelser
- Brug \\n for linjeskift inde i content-strengen (JSON-format)
- Inkluder altid pladsholdere som [Dit navn], [Dato], [Adresse] osv.
- Skriv det KOMPLETTE dokument — aldrig kun en kort skabelon
- Alle tekster på dansk

─── OPGAVER FORMAT ───
Skriv din besked FØRST. Tilføj derefter opgaver i slutningen:

[TASKS]
[{"title": "Kontakt Familieretshuset", "description": "Ring og hør om jeres muligheder", "priority": "high", "days": 7, "type": "kommune", "reasoning": "Første skridt i processen"}]
[/TASKS]

Tags og format:
- Åbningstag: [TASKS] — lukningstag: [/TASKS]
- JSON array: [{"title": "..."}, {"title": "..."}]
- Prioriteter: low, medium, high, critical
- "days": antal dage til frist
- Typer: samvaer, bolig, oekonomi, juridisk, kommune, dokument, forsikring, personlig

─── HVAD DU SKAL GØRE NU (baseret på samtalens fase) ───
{$phaseInstruction}

{$this->buildKnowledgeSection($ragContext)}
PROMPT;
    }

    private function getPhaseInstruction(int $aiTurn): string
    {
        return match(true) {
            $aiTurn === 0 => <<<'PHASE'
FASE 1 — FØRSTE SVAR:
Dette er dit allerførste svar. Du kender endnu ikke situationen.

Gør følgende:
1. Anerkend det brugeren har skrevet med varme og ægthed (1-2 sætninger)
2. Forklar at du gerne vil stille 3 korte spørgsmål for at forstå situationen ordentligt — så du kan hjælpe bedre
   Eksempel: "Inden vi går videre, vil jeg gerne stille dig tre korte spørgsmål, så jeg forstår din situation rigtigt — så kan jeg hjælpe dig meget mere præcist."
3. Stil dit FØRSTE spørgsmål. Vælg det vigtigste baseret på hvad brugeren har skrevet:
   - Har I børn under 18 år? Og i så fald hvor mange og i hvilke aldre?
   - Eller: Er I enige om at gå fra hinanden, eller er der modstand fra den ene side?
   - Eller: Bor I stadig sammen, eller er en af jer allerede flyttet ud?
4. INGEN opgaver i dette svar.
PHASE,

            $aiTurn === 1 => <<<'PHASE'
FASE 2 — ANDET SVAR (spørgsmål 2 af 3):
Du har nu hørt svaret på dit første spørgsmål.

Gør følgende:
1. Anerkend svaret kort og konkret (1 sætning)
2. Stil dit ANDET spørgsmål. Vælg det næstmest relevante, f.eks.:
   - Hvad er den største bekymring for dig lige nu — børn, bolig, økonomi eller noget andet?
   - Eller: Har I fælles ejendom, opsparing eller gæld der skal deles?
   - Eller: Er der vold, trusler eller sikkerhedsmæssige bekymringer i billedet?
3. Fortæl at du har ét spørgsmål mere efter dette.
4. INGEN opgaver endnu.
PHASE,

            $aiTurn === 2 => <<<'PHASE'
FASE 3 — TREDJE SVAR (spørgsmål 3 af 3):
Du har nu hørt to svar. Du er ved at samle billedet.

Gør følgende:
1. Anerkend svaret kort (1 sætning)
2. Stil dit TREDJE og sidste spørgsmål. F.eks.:
   - Hvordan har du det emotionelt lige nu — er du i krise, eller er du nogenlunde okay?
   - Eller: Har du nogen støtte omkring dig — familie, venner, advokat?
   - Eller: Hvad er det vigtigste for dig at få styr på i de næste 2-3 uger?
3. Fortæl at du vil give et samlet overblik og opgaver når du har svaret.
4. INGEN opgaver endnu.
PHASE,

            $aiTurn === 3 => <<<'PHASE'
FASE 4 — FJERDE SVAR (sammenfatning + opgaveplan):
Du har nu lært situationen at kende. Dette svar HAR TO DELE — begge er obligatoriske.

DEL 1 — SAMMENFATNING (2-3 sætninger i prosa):
Opsummer hvad du har forstået. Vær specifik og personlig.
Eksempel: "Tak for at du delte det med mig. Jeg forstår at I skal skilles, at I har et barn på 8 år, og at det er bopæl og økonomi der fylder mest for dig lige nu."

DEL 2 — OPGAVER (OBLIGATORISK — dette er ikke valgfrit):
Du SKAL afslutte dit svar med [TASKS]-blokken. Ingen undtagelse.
Skriv SÅ MANGE opgaver som situationen kræver — ingen øvre grænse. Opgaverne skal være skræddersyet til PRÆCIS denne persons situation baseret på hvad du har lært.
Opgaverne må IKKE være generiske — de skal referere til det personen faktisk har fortalt.

Formatet skal se PRÆCIS sådan ud:

[TASKS]
[{"title": "Ring til Familieretshuset", "description": "...", "priority": "high", "days": 7, "type": "kommune", "reasoning": "..."}, {"title": "...", "description": "...", "priority": "medium", "days": 14, "type": "bolig", "reasoning": "..."}]
[/TASKS]

REGLER:
- [TASKS] starter på en ny linje
- Indholdet er ET JSON-array med objekter
- [/TASKS] afslutter blokken
- Dit svar er IKKE færdigt uden denne blok
PHASE,

            default => <<<'PHASE'
FASE 5+ — LØBENDE SAMTALE:
Du kender nu situationen. Opfør dig som en klog ven der følger med i processen.

VIGTIG REGEL OM FORMAT: Skriv ALTID i sammenhængende prosa — ALDRIG nummererede lister (1. 2. 3.) eller bullet points (- *). En liste er ikke et svar. Tal direkte til personen i naturlige sætninger.

Svar på præcis det der bliver spurgt. Vær empatisk men direkte.
Hvis brugeren beder om en besked/SMS: hjælp konstruktivt med formuleringen.
Hvis brugeren beder om et dokument: opret det fuldt ud med [DOCUMENT] tagget.

OPGAVE-REGEL — STRENGT OBLIGATORISK:
Når dit svar indeholder råd, handlinger eller næste skridt — SKAL du altid slutte med [TASKS]-blokken.
Trigger-ord: "kontakt", "ring", "send", "ansøg", "overvej", "bør", "vigtigt", "næste skridt", "første skridt", "du kan", "anbefaler".
Undtagelse: Kun hvis svaret er ren følelsesmæssig spejling uden nogen handlingsanvisning.

FORMAT — kopier dette præcist:
[TASKS]
[{"title": "Kontakt Familieretshuset", "description": "Ring og book et møde", "priority": "high", "days": 7, "type": "kommune", "reasoning": "Første skridt"}, {"title": "Overvej terapi", "description": "Find en børnepsykolog", "priority": "medium", "days": 14, "type": "personlig", "reasoning": "Støtte til børnene"}]
[/TASKS]

Regler: [TASKS] på egen linje, ET JSON-array, [/TASKS] på egen linje. Prioriteter: low/medium/high/critical. Typer: samvaer/bolig/oekonomi/juridisk/kommune/dokument/forsikring/personlig.
PHASE,
        };
    }

    private function getHistory(CaseModel $case)
    {
        return Conversation::where('case_id', $case->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();
    }

    private function formatHistory($history): array
    {
        $messages = [];

        // No few-shot injected — phase system handles structure via system prompt

        foreach ($history as $msg) {
            // Clean old messages that contain raw tags to prevent AI from copying bad patterns
            $content = $msg->content;
            $content = preg_replace('/\[?\/?(?:TASKS|DOCUMENT)\]?\s*\[?\s*[\{\[].*?[\}\]]\s*\]?\s*\[?\/?(?:TASKS|DOCUMENT)\]?/s', '', $content);
            $content = preg_replace('/\[?\/?(?:TASKS|DOCUMENT)\]?/i', '', $content);
            $content = trim($content);

            if ($content) {
                $messages[] = [
                    'role' => $msg->role,
                    'content' => $content,
                ];
            }
        }

        return $messages;
    }

    /**
     * Combine dynamic RAG context with the static knowledge base.
     * RAG chunks are shown first so the model prioritises fresh, scraped data.
     */
    private function buildKnowledgeSection(string $ragContext): string
    {
        $staticKnowledge = $this->getDanishFamilyLawKnowledge();

        if (empty(trim($ragContext))) {
            // No scraped chunks found – fall back to static knowledge only
            return $staticKnowledge;
        }

        return <<<SECTION
{$ragContext}

─────────────────────────────────────────────────────────────────
SUPPLERENDE VIDENSBASE (intern reference):
─────────────────────────────────────────────────────────────────
{$staticKnowledge}
SECTION;
    }

    /**
     * Danish family law knowledge base injected into system prompts.
     */
    private function getDanishFamilyLawKnowledge(): string
    {
        return <<<'LAW'
DANSK FAMILIERET - VIDENSBASE:

═══ SKILSMISSE & SEPARATION ═══

SEPARATION:
- Man kan søge separation hos Familieretshuset uden ægtefællens samtykke
- Separationsperioden er 6 måneder - herefter kan man søge skilsmisse
- Under separation er man stadig gift juridisk, men bodelingen kan starte
- Man kan IKKE gifte sig igen under separation
- Begge parter skal flytte fra hinanden inden 3 måneder (ellers bortfalder separationen)
- Arveretten ophører fra separationstidspunktet
- Troskabspligten bortfalder fra separationstidspunktet
- Formuefællesskabet ophører fra det tidspunkt ansøgningen sendes
- Ansøgning: Digitalt via familieretshuset.dk
- Gebyr: 875 kr. grundgebyr + 2.150 kr. hvis vilkårsforhandling er nødvendig

DIREKTE SKILSMISSE (uden separation):
- Kræver enighed mellem parterne om alle vilkår, ELLER
- Hvis en part har udøvet vold, ELLER
- Hvis en part har begået bigami, ELLER
- Hvis parterne har levet adskilt i 2 år, ELLER
- Utroskab, ELLER
- Børnebortførelse
- Gebyr: 875 kr. grundgebyr + evt. 2.150 kr. for vilkårsforhandling

VILKÅR VED SKILSMISSE:
- Parterne SKAL tage stilling til: ægtefællebidrag og evt. retten til lejebolig
- Hvis enighed om alle vilkår: bevilling (hurtigere, typisk 2-4 uger)
- Hvis uenighed: Familieretshuset indkalder til vilkårsforhandling (ekstra 2.150 kr.)
- Klageadgang: 4 uger til at klage over afgørelse via Familieretshusets klageskema

═══ FAMILIERETSHUSET ═══

SAGSTYPER (trin-systemet):
1. § 6 - Enkle sager: Parterne er enige → afgøres administrativt
2. § 7 - Mindre komplekse sager: Nogen uenighed → rådgivning og mægling
3. § 8 - Komplekse sager: Alvorlig uenighed/bekymring → grundig behandling, evt. børnesagkyndig
4. § 9 - Sager til familieretten: De mest komplekse → sendes til retten

KONTAKT:
- Telefon: 72 56 70 00 (mandag-fredag 9-14)
- Web: familieretshuset.dk
- Digitale selvbetjeningsløsninger på borger.dk

BØRNEENHEDEN:
- Børn over 10 år SKAL høres (børnesamtale)
- Børn under 10 år KAN høres hvis det skønnes relevant
- Kontaktperson kan tildeles barnet

═══ FORÆLDREMYNDIGHED ═══

FÆLLES FORÆLDREMYNDIGHED (standard):
- Begge forældre har forældremyndighed automatisk hvis gift
- Fælles forældremyndighed fortsætter efter skilsmisse som udgangspunkt
- Begge forældre skal være enige om væsentlige beslutninger: skole, religion, flytning, pas, sundhed

ENEFORÆLDREMYNDIGHED:
- Kan kun tilkendes af Familieretshuset/familieretten
- Kræver tungtvejende grunde: vold, misbrug, manglende samarbejdsevne
- Den uden forældremyndighed har stadig ret til samvær og oplysninger om barnet

BOPÆLSFORÆLDER:
- Barnet har officiel bopæl hos én forælder
- Bopælsforælderen bestemmer dagligdags ting (tøj, mad, sengetider)
- Bopælsforælderen modtager børne- og ungeydelse
- Ved uenighed kan Familieretshuset afgøre bopælsspørgsmålet

FLYTNING:
- Bopælsforælderen SKAL varsle flytning 6 uger FØR (varslingsregel)
- Den anden forælder kan anmode Familieretshuset om at flytte bopælen

═══ SAMVÆR ═══

SAMVÆRSRET:
- Barnet har ret til samvær med begge forældre (barnets ret, ikke forældrenes)
- Typiske samværsordninger:
  - 7/7-ordning: Barnet er en uge hos hver forælder
  - 9/5-ordning: 9 dage hos bopæl, 5 dage hos samvær
  - 10/4-ordning: 10 dage hos bopæl, 4 dage hos samvær
  - 12/2-ordning: Hver anden weekend (fredag-søndag)
  - Udvidet samvær: Hver anden weekend + en hverdag
- Ferie og helligdage deles typisk ligeligt

SAMVÆRSAFTALE BØR INDEHOLDE:
1. Hverdagssamvær (hvilke dage, tidspunkter)
2. Weekendsamvær
3. Feriesamvær (sommer, jul, påske, efterår, vinter)
4. Helligdage og fødselsdage
5. Afhentning/aflevering (sted og tidspunkt)
6. Transport (hvem kører)
7. Kommunikation med barnet mellem samvær
8. Ændringer og opsigelsesvarsel

OVERVÅGET SAMVÆR:
- Kan fastsættes hvis der er bekymring for barnets sikkerhed
- Støttet samvær: En professionel er til stede
- Overvåget samvær: Strengere overvågning
- Kan fastsættes midlertidigt mens sagen behandles

═══ BØRNEBIDRAG ═══

GRUNDREGLER:
- Den forælder barnet IKKE bor hos betaler børnebidrag
- Normalbidrag (2024): 1.640 kr./måned (grundbeløb) + tillæg
- Bidraget fastsættes ud fra bidragsyders indkomst
- Bidragspligt indtil barnet fylder 18 år (kan forlænges til 24 ved uddannelse)

INDKOMSTBASERET BIDRAG (ca. retningslinjer 2024):
- Under ca. 500.000 kr./år: Normalbidrag
- 500.000-700.000 kr./år: Normalbidrag + 100%
- 700.000-900.000 kr./år: Normalbidrag + 200%
- Over 900.000 kr./år: Normalbidrag + 300%

VED 7/7-ORDNING:
- Som udgangspunkt betaler ingen børnebidrag ved lige delt samvær
- Undtagelse: Stor indkomstforskel kan medføre bidrag alligevel

KONFIRMATIONSBIDRAG:
- 3x normalbidraget
- Betales det år barnet fylder 14 (eller konfirmeres)

═══ ÆGTEFÆLLEBIDRAG ═══

- Kan tilkendes hvis der er stor forskel i parternes økonomi
- Typisk tidsbegrænset (1-10 år afhængig af ægteskabets længde)
- Bortfalder ved nyt ægteskab eller registreret partnerskab
- Kan nedsættes/ophæves ved ændrede forhold
- Fastsættes af Familieretshuset ved uenighed

═══ BODELING ═══

OPHØRSDATO (VIGTIGT):
- Formuefællesskabet ophører ved udgangen af det døgn, ansøgningen om separation/skilsmisse indgives
- Alt erhvervet EFTER ophørsdatoen indgår IKKE i delingen
- Parterne kan dog aftale en anden ophørsdato

DELINGSFORMUE (standard):
- Alt erhvervet UNDER ægteskabet deles lige (50/50)
- Gæld modregnes i den enkeltes bodel
- Frist: Bodeling SKAL kræves inden 1 år efter separationen

BEREGNING AF BODELING:
- Begge parter positive bodel: Værdier udlignes så begge ender med samme beløb
  Eksempel: Part A har 10.000 kr., Part B har 40.000 kr. → B betaler 15.000 kr. til A → begge ender med 25.000 kr.
- Én positiv, én negativ bodel: Kun den positive deles. Gælden hos den anden deles IKKE
- Begge negative bodele: Ingen udligning - hver part beholder sit underskud

SÆREJE:
- Kræver ægtepagt (skal tinglyses)
- Fuldstændigt særeje: Deles ikke ved skilsmisse
- Kombinationssæreje: Deles ved død men ikke ved skilsmisse
- Skilsmissesæreje: Deles ikke ved skilsmisse men ved død
- Hvis en part har betydeligt særeje der stiller den anden urimelig ringe, kan skifteretten påbyde kompensation

HVAD DELES:
- Bolig (ejerbolig, andelsbolig)
- Bil, opsparing, aktier
- Indbo og møbler
- Virksomhed/forretning

HVAD DELES IKKE:
- Særeje (ved ægtepagt)
- Arv/gave modtaget med særejeklausul
- Personlige erstatninger
- Pensionsopsparinger tilhører som udgangspunkt den oprindelige ejer

BOLIGFORDELING:
- Ingen automatisk ret til at blive boende
- Familieretshuset kan tilkende midlertidig bopælsret
- Den der "mest trænger til" boligen kan have fortrinsret
- Lejeboliger: Familieretshuset kan afgøre hvem der overtager lejemålet

SKIFTERETTEN (ved uenighed om bodeling):
- Grundafgift: 750 kr.
- Bobehandler: 0,5% skifteafgift (maks 10.000 kr.) + 1.500 kr. retsafgift
- Fri proces kan søges ved lav indkomst

═══ ØKONOMI UNDER PROCESSEN ═══

- Forsørgelsespligt: Begge ægtefæller har forsørgelsespligt under separation
- Børne- og ungeydelse: Udbetales til bopælsforælderen
- Boligstøtte: Kan søges ved ændret husstandsindkomst
- Enkeltydelse fra kommunen: Ved akut økonomisk krise
- Fri proces: Mulighed for gratis advokathjælp ved lav indkomst

BØRN I MISTRIVSEL:
- Kommunen har pligt til at hjælpe børn der mistrives
- Underretningspligt: Alle borgere og fagpersoner har pligt til at underrette kommunen
- Børn kan selv henvende sig til Børns Vilkår (116 111) eller kommunen
- Kommunen kan tilbyde: familiebehandling, kontaktperson, aflastning, anbringelse

═══ VIGTIGE FRISTER ═══

- Varsel om flytning: 6 uger før
- Bodeling: Kræves inden 1 år efter separation
- Børnebidrag: Kan kræves op til 6 måneder bagud
- Anke af Familieretshusets afgørelse: 4 uger
- Skilsmisse efter separation: Tidligst efter 6 måneder

═══ VOLD & SIKKERHED ═══

FORMER FOR VOLD I NÆRE RELATIONER:
- Fysisk vold: Slag, spark, kvælertag, fastholdelse, skub
- Psykisk vold: Trusler, nedgøring, kontrol, isolation, manipulation, gaslighting
- Økonomisk vold: Kontrol over økonomi, tilbageholde penge, tvinge til gæld
- Seksuel vold: Uønsket seksuel kontakt, voldtægt, tvang
- Digital vold: Overvågning, hacking, deling af private billeder, kontrol via telefon
- Stalking: Vedvarende forfølgelse, overvågning, uønsket kontakt
- Materiel vold: Ødelæggelse af ejendele, smadre ting

STRAFFELOVEN §266 - TRUSLER:
- Det er strafbart at true med at begå en strafbar handling på en måde der er egnet til at fremkalde alvorlig frygt for ens liv, helbred eller velfærd
- Straf: Bøde eller fængsel op til 2 år
- Trusler kan være mundtlige, skriftlige eller digitale (SMS, sociale medier)

ANMELDELSE TIL POLITIET:
- Ring 112 ved akut fare for liv
- Ring 114 ved trusler uden øjeblikkelig risiko
- Møde personligt op på politistationen
- Tilhold/straksforbud kan søges via politiet

HJÆLPELINJER OG RESSOURCER:
- Lev Uden Vold hotline: 1888 (døgnåben) - rådgivning og krisehjælp
- LOKK (Landsorganisationen af Kvindekrisecentre): Tlf. 33 11 60 67
- Mandekrisecentret: Tlf. 70 11 45 03
- Børns Vilkår: Tlf. 116 111 (for børn)
- Krisecenter: Alle kommuner har krisecenter-tilbud
- Juridisk rådgivning tilgængelig via levudenvold.dk

DOKUMENTATION (VIGTIGT):
- Gem ALLE beviser: SMS, emails, chatbeskeder, voicemails
- Tag fotos af skader og ødelagte ting
- Gem politianmeldelser og lægeerklæringer
- Fortæl en betroet person om situationen
- Overvej at føre dagbog over hændelser med datoer

═══ NYTTIGE SKABELONER ═══

SAMVÆRSAFTALE - STRUKTUR:
1. Parternes navne og CPR-numre
2. Børnenes navne og CPR-numre
3. Bopælsforælder
4. Hverdagssamvær (dage, tidspunkter)
5. Weekendsamvær (hvilke weekender, tidspunkter)
6. Ferieplan (sommer: uge X-Y, jul: lige/ulige år, påske, efterår)
7. Helligdage og særlige dage (fødselsdage, grundlovsdag)
8. Afhentning og aflevering (sted, tidspunkt, hvem transporterer)
9. Kommunikation (app, telefon, SMS)
10. Ændringer (varsel, procedure)
11. Dato og underskrifter

BODELINGSAFTALE - STRUKTUR:
1. Parternes navne og CPR-numre
2. Dato for separation/skilsmisse
3. Fast ejendom (adresse, vurdering, hvem overtager/sælges)
4. Bil(er) (reg.nr., værdi, hvem overtager)
5. Bankkonti (bank, kontonr., saldo, fordeling)
6. Pensioner (selskab, type, deling/kompensation)
7. Gæld (kreditor, beløb, hvem hæfter)
8. Indbo (fordeling af møbler, elektronik mv.)
9. Udligningsbeløb (hvis en part overtager mere end halvdelen)
10. Dato og underskrifter

SKILSMISSEPAPIRER VIA FAMILIERETSHUSET:
1. Log ind på familieretshuset.dk med MitID
2. Vælg "Ansøg om separation" eller "Ansøg om skilsmisse"
3. Udfyld formularen (begge parter skal underskrive ved enighed)
4. Betal gebyr
5. Vent på bekræftelse (typisk 2-4 uger ved enighed)
LAW;
    }

    public function uploadDocument(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate([
            'file'    => 'required|file|max:10240|mimes:pdf,txt,jpg,jpeg,png',
            'case_id' => 'nullable|exists:cases,id',
        ]);

        $file = $request->file('file');
        if (!$this->validateFileMagicBytes($file)) {
            return response()->json(['message' => 'Filtypen matcher ikke indholdet.'], 422);
        }

        $user = auth()->user();

        $limit = $user->ai_messages_limit ?? 50;
        $used  = $user->ai_messages_used  ?? 0;
        if ($used >= $limit) {
            return response()->json([
                'error'   => 'message_limit_reached',
                'message' => 'Du har brugt alle dine AI-beskeder denne måned. Opgradér din plan for at fortsætte.',
            ], 429);
        }

        $originalFilename = preg_replace('/[^\w\s\-\.]/', '_', $file->getClientOriginalName());
        $mimeType         = $file->getMimeType();
        $extension        = strtolower($file->getClientOriginalExtension());
        $filename         = uniqid() . '.' . $extension;
        $storagePath      = "documents/{$user->id}/{$filename}";
        $absolutePath     = storage_path("app/{$storagePath}");

        Storage::put($storagePath, file_get_contents($file->getRealPath()));

        // Create or load case
        if (!empty($request->input('case_id'))) {
            $case = CaseModel::where('id', $request->input('case_id'))
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $case = CaseModel::create([
                'user_id'           => $user->id,
                'case_type'         => 'divorce',
                'situation_summary' => "Uploadet dokument: {$originalFilename}",
                'status'            => 'active',
            ]);
        }

        // Extract text
        $extractedText = '';
        try {
            if ($extension === 'pdf') {
                $parser        = new \Smalot\PdfParser\Parser();
                $pdfDoc        = $parser->parseFile($absolutePath);
                $extractedText = $pdfDoc->getText();

                // Fallback for scanned PDFs: convert first page to image → vision OCR
                if (empty(trim($extractedText))) {
                    $extractedText = $this->extractTextFromScannedPdf($absolutePath);
                }
            } elseif ($extension === 'txt') {
                $raw = file_get_contents($absolutePath);
                // Detect and convert UTF-16 (LE/BE) — common for Windows-created TXT files
                if (str_starts_with($raw, "\xFF\xFE")) {
                    $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
                } elseif (str_starts_with($raw, "\xFE\xFF")) {
                    $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
                }
                $extractedText = $raw;
            } else {
                // JPG / PNG — use Mistral vision
                $base64   = base64_encode(file_get_contents($absolutePath));
                $mimeVis  = in_array($extension, ['jpg', 'jpeg']) ? 'image/jpeg' : 'image/png';
                $extractedText = $this->extractTextFromImage($base64, $mimeVis);
            }
        } catch (\Throwable $e) {
            Log::error('Document text extraction failed', ['error' => $e->getMessage()]);
        }

        $extractedText = mb_substr(trim($extractedText), 0, 8000);

        // Save Document record
        \App\Models\Document::create([
            'user_id'           => $user->id,
            'case_id'           => $case->id,
            'filename'          => $filename,
            'original_filename' => $originalFilename,
            'mime_type'         => $mimeType,
            'file_size_bytes'   => $file->getSize(),
            'storage_path'      => $storagePath,
            'document_type'     => 'upload',
            'processing_status' => 'completed',
            'extracted_text'    => $extractedText,
        ]);

        // Save user turn in conversation
        $userContent = "📎 Uploadet dokument: **{$originalFilename}**";
        Conversation::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'role'    => 'user',
            'content' => $userContent,
        ]);

        // Build conversation history & AI turn count
        $history = $this->getHistory($case);
        $aiTurn  = $history->where('role', 'assistant')->count();

        // Inject document text into system prompt
        $truncated = mb_substr($extractedText, 0, 4000);
        $noTextNote = empty($truncated)
            ? "\n\nBemærk: Ingen tekst kunne udtrækkes fra denne fil. Dokumentet kan være krypteret eller i et format der ikke understøttes."
            : '';

        $docSection = <<<SECTION


─── UPLOADET DOKUMENT ───
Brugeren har uploadet dokumentet: "{$originalFilename}"

Indhold:
{$truncated}{$noTextNote}

Analyser dokumentet grundigt. Opsummer hvad det handler om, fremhæv vigtige punkter, frister og handlingspunkter — og opret relevante opgaver.
SECTION;

        $systemPrompt    = $this->getSystemPrompt($case, '', $aiTurn) . $docSection;
        $mistralMessages = $this->formatHistory($history);
        array_unshift($mistralMessages, ['role' => 'system', 'content' => $systemPrompt]);

        $caseId = $case->id;

        return response()->stream(function () use (
            $mistralMessages, $case, $user, $aiTurn, $history, $originalFilename, $caseId
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $payload = json_encode([
                'model'      => 'mistral-small-latest',
                'messages'   => $mistralMessages,
                'max_tokens' => 3000,
                'stream'     => true,
            ]);

            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Authorization: Bearer " . config('services.mistral.key') . "\r\n"
                                     . "Content-Type: application/json\r\n",
                    'content'       => $payload,
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);

            $fullContent  = '';
            $streamFailed = false;

            try {
                $fp = fopen('https://api.mistral.ai/v1/chat/completions', 'r', false, $ctx);
                if (!$fp) {
                    $streamFailed = true;
                } else {
                    while (!feof($fp)) {
                        $line = fgets($fp, 4096);
                        if ($line === false) break;
                        $line = trim($line);
                        if (!str_starts_with($line, 'data: ')) continue;
                        $data = substr($line, 6);
                        if ($data === '[DONE]') break;
                        $chunk = json_decode($data, true);
                        $text  = $chunk['choices'][0]['delta']['content'] ?? '';
                        if ($text !== '') {
                            $fullContent .= $text;
                            echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $text]) . "\n\n";
                            flush();
                        }
                    }
                    fclose($fp);
                }
            } catch (\Throwable $e) {
                Log::error('Mistral stream error (document upload)', ['error' => $e->getMessage()]);
                $streamFailed = true;
            }

            if ($streamFailed) {
                $fullContent = 'Beklager, jeg kunne ikke analysere dokumentet. Prøv igen om lidt.';
                echo 'data: ' . json_encode(['type' => 'chunk', 'text' => $fullContent]) . "\n\n";
                flush();
            }

            [$displayMessage, $aiDocument, $createdTasks] = $this->parseAndPersist(
                $fullContent, $case, $user, $aiTurn, $history, [], $originalFilename
            );

            echo 'data: ' . json_encode([
                'type'     => 'done',
                'message'  => $displayMessage,
                'case_id'  => $caseId,
                'tasks'    => collect($createdTasks)->map(fn ($t) => [
                    'id'          => $t->id,
                    'title'       => $t->title,
                    'description' => $t->description,
                    'priority'    => $t->priority,
                    'due_date'    => $t->due_date?->format('Y-m-d'),
                ]),
                'document' => $aiDocument,
            ]) . "\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Convert the first page of a scanned PDF to JPEG and run vision OCR.
     * Tries Imagick first, then Ghostscript via exec().
     */
    private function extractTextFromScannedPdf(string $pdfPath): string
    {
        $jpegData = null;

        // Method 1: PHP Imagick extension (+ Ghostscript installed on system)
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick();
                $im->setResolution(150, 150);
                $im->readImage($pdfPath . '[0]'); // first page only
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(85);
                $jpegData = $im->getImageBlob();
                $im->clear();
            } catch (\Throwable $e) {
                Log::warning('Imagick PDF→JPEG failed', ['error' => $e->getMessage()]);
            }
        }

        // Method 2: Ghostscript via exec() — works on Windows XAMPP if GS is installed
        if (!$jpegData && function_exists('exec')) {
            $tmpJpeg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pdf_ocr_') . '.jpg';

            // Locate gswin64c.exe (tries common Windows install paths)
            $gsBin = $this->findGhostscript();

            if ($gsBin) {
                $cmd = $gsBin
                    . ' -dNOPAUSE -dBATCH -sDEVICE=jpeg -dFirstPage=1 -dLastPage=1'
                    . ' -r150 -dJPEGQ=85'
                    . ' -sOutputFile=' . escapeshellarg($tmpJpeg)
                    . ' ' . escapeshellarg($pdfPath)
                    . ' 2>&1';

                exec($cmd, $output, $code);

                if ($code === 0 && file_exists($tmpJpeg) && filesize($tmpJpeg) > 0) {
                    $jpegData = file_get_contents($tmpJpeg);
                } else {
                    Log::warning('Ghostscript PDF→JPEG failed', [
                        'code'   => $code,
                        'output' => implode("\n", $output),
                    ]);
                }

                if (file_exists($tmpJpeg)) {
                    unlink($tmpJpeg);
                }
            }
        }

        if (!$jpegData) {
            return '';
        }

        return $this->extractTextFromImage(base64_encode($jpegData), 'image/jpeg');
    }

    /** Find Ghostscript binary on Windows or Unix. */
    private function findGhostscript(): ?string
    {
        // Unix / Linux / Mac
        if (PHP_OS_FAMILY !== 'Windows') {
            foreach (['gs', '/usr/bin/gs', '/usr/local/bin/gs'] as $bin) {
                exec("which {$bin} 2>/dev/null", $out, $code);
                if ($code === 0 && !empty($out[0])) return $bin;
            }
            return null;
        }

        // Windows — check common GhostScript install directories
        $programFiles = [
            getenv('ProgramFiles')       ?: 'C:\\Program Files',
            getenv('ProgramFiles(x86)')  ?: 'C:\\Program Files (x86)',
        ];

        foreach ($programFiles as $pf) {
            $gsDir = $pf . '\\gs';
            if (!is_dir($gsDir)) continue;

            // Iterate version folders (e.g. gs10.04.0, gs9.56.1)
            $versions = glob($gsDir . '\\gs*', GLOB_ONLYDIR) ?: [];
            // Newest first
            rsort($versions);

            foreach ($versions as $vDir) {
                $candidate = $vDir . '\\bin\\gswin64c.exe';
                if (file_exists($candidate)) return '"' . $candidate . '"';
                $candidate32 = $vDir . '\\bin\\gswin32c.exe';
                if (file_exists($candidate32)) return '"' . $candidate32 . '"';
            }
        }

        return null;
    }

    private function extractTextFromImage(string $base64, string $mimeType): string
    {
        $payload = json_encode([
            'model'    => 'pixtral-12b-2409',
            'messages' => [[
                'role'    => 'user',
                'content' => [
                    [
                        'type'      => 'image_url',
                        'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Beskriv og transskribér al tekst du kan se i dette billede. Returner kun teksten fra billedet.',
                    ],
                ],
            ]],
            'max_tokens' => 1500,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Authorization: Bearer " . config('services.mistral.key') . "\r\n"
                                 . "Content-Type: application/json\r\n",
                'content'       => $payload,
                'timeout'       => 25,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $response = @file_get_contents('https://api.mistral.ai/v1/chat/completions', false, $ctx);
        if (!$response) return '';

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function destroyCase(CaseModel $case): JsonResponse
    {
        $user = auth()->user();

        if ($case->user_id !== $user->id) {
            abort(403);
        }

        // Delete conversations, tasks, and documents linked to this case
        Conversation::where('case_id', $case->id)->delete();
        Task::where('case_id', $case->id)->delete();
        \App\Models\Document::where('case_id', $case->id)->delete();
        $case->delete();

        return response()->json(['success' => true]);
    }

    public function destroyPeriod(string $period): JsonResponse
    {
        $user = auth()->user();

        $now = new \DateTime();
        $today = (new \DateTime())->setTime(0, 0);

        switch ($period) {
            case 'today':
                $from = $today;
                $to = $now;
                break;
            case 'yesterday':
                $from = (clone $today)->modify('-1 day');
                $to = $today;
                break;
            case 'week':
                $from = (clone $today)->modify('-7 days');
                $to = (clone $today)->modify('-1 day');
                break;
            case 'month':
                $from = (clone $today)->modify('-30 days');
                $to = (clone $today)->modify('-7 days');
                break;
            case 'older':
                $from = new \DateTime('2000-01-01');
                $to = (clone $today)->modify('-30 days');
                break;
            default:
                return response()->json(['error' => 'Invalid period'], 400);
        }

        $cases = CaseModel::where('user_id', $user->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->get();

        foreach ($cases as $case) {
            Conversation::where('case_id', $case->id)->delete();
            Task::where('case_id', $case->id)->delete();
            \App\Models\Document::where('case_id', $case->id)->delete();
            $case->delete();
        }

        return response()->json(['success' => true, 'deleted' => $cases->count()]);
    }

    /**
     * Validate that uploaded file content matches its claimed extension using magic bytes.
     */
    private function validateFileMagicBytes(\Illuminate\Http\UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if (!$path || !file_exists($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            return false;
        }
        $header = fread($handle, 12);
        fclose($handle);

        if (strlen($header) < 4) {
            // Very small file — allow only .txt
            $ext = strtolower($file->getClientOriginalExtension());
            return $ext === 'txt';
        }

        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'pdf'        => str_starts_with($header, '%PDF'),
            'jpg', 'jpeg' => ord($header[0]) === 0xFF && ord($header[1]) === 0xD8 && ord($header[2]) === 0xFF,
            'png'        => str_starts_with($header, "\x89PNG"),
            'txt'        => $this->looksLikeText($path),
            default      => false,
        };
    }

    private function looksLikeText(string $path): bool
    {
        $sample = file_get_contents($path, false, null, 0, 1024);
        if ($sample === false) {
            return false;
        }
        // Allow UTF-8, UTF-16 BOM, and plain ASCII; reject if >10% non-text bytes
        if (str_starts_with($sample, "\xFF\xFE") || str_starts_with($sample, "\xFE\xFF")) {
            return true; // UTF-16
        }
        $nonText = 0;
        $len = strlen($sample);
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($sample[$i]);
            if ($byte < 0x09 || ($byte > 0x0D && $byte < 0x20 && $byte !== 0x1B)) {
                $nonText++;
            }
        }
        return ($nonText / max($len, 1)) < 0.10;
    }
}
