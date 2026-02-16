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

        $conversations = $activeCase
            ? $activeCase->conversations()->orderBy('created_at', 'asc')->get()
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

    public function taskChatSend(Request $request, Task $task): JsonResponse
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

        try {
            $messages = [];
            foreach ($history as $msg) {
                $messages[] = [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ];
            }

            array_unshift($messages, [
                'role' => 'system',
                'content' => $this->getTaskSystemPrompt($task, $taskRagContext),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.mistral.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => 'mistral-small-latest',
                'messages' => $messages,
                'max_tokens' => 2000,
            ]);

            if ($response->successful()) {
                $aiMessage = $response->json('choices.0.message.content');
            } else {
                Log::error('Mistral API error (task chat)', ['status' => $response->status()]);
                $aiMessage = "Beklager, jeg kunne ikke generere et svar lige nu. Prøv igen om lidt.";
            }
        } catch (\Exception $e) {
            Log::error('Mistral exception (task chat)', ['message' => $e->getMessage()]);
            $aiMessage = "Beklager, jeg kunne ikke generere et svar lige nu. Prøv igen om lidt.";
        }

        // Parse document from response
        $displayMessage = $aiMessage;
        $document = null;

        if (preg_match('/\[\/?DOCUMENT\]\s*(.+?)\s*\[\/?DOCUMENT\]/s', $displayMessage, $docMatch)) {
            $displayMessage = trim(preg_replace('/\[\/?DOCUMENT\]\s*.+?\s*\[\/?DOCUMENT\]/s', '', $displayMessage));
            $jsonStr = trim($docMatch[1]);
            $docJson = json_decode($jsonStr, true);

            if (!$docJson) {
                $fixed = str_replace(["\r\n", "\r", "\n"], '\n', $jsonStr);
                $docJson = json_decode($fixed, true);
            }

            if (!$docJson) {
                if (preg_match('/"title"\s*:\s*"([^"]+)"/i', $jsonStr, $tMatch) &&
                    preg_match('/"content"\s*:\s*"(.+)"\s*\}$/s', $jsonStr, $cMatch)) {
                    $docJson = [
                        'title' => $tMatch[1],
                        'content' => str_replace(["\r\n", "\r", "\n"], "\n", trim($cMatch[1])),
                    ];
                }
            }

            if ($docJson && isset($docJson['title'])) {
                $document = [
                    'title' => $docJson['title'],
                    'content' => $docJson['content'] ?? '',
                ];
            }
        }

        // Parse tasks from response
        $createdTasks = [];
        if (preg_match('/\[TASKS\]\s*(.+?)\s*\[\/TASKS\]/s', $displayMessage, $taskMatch)) {
            $displayMessage = trim(preg_replace('/\[TASKS\]\s*.+?\s*\[\/TASKS\]/s', '', $displayMessage));
            $rawTasks = trim($taskMatch[1]);
            $tasksJson = json_decode($rawTasks, true);

            if (!is_array($tasksJson)) {
                $fixed = str_replace(["\r\n", "\r", "\n"], ' ', $rawTasks);
                $tasksJson = json_decode($fixed, true);
            }
            if ($tasksJson && !isset($tasksJson[0]) && isset($tasksJson['title'])) {
                $tasksJson = [$tasksJson];
            }
            if (is_array($tasksJson)) {
                foreach ($tasksJson as $taskData) {
                    if (!isset($taskData['title'])) continue;
                    $daysUntilDue = isset($taskData['days']) ? (int) $taskData['days'] : 7;
                    $newTask = Task::create([
                        'case_id'      => $task->case_id,
                        'user_id'      => $user->id,
                        'title'        => $taskData['title'],
                        'description'  => $taskData['description'] ?? null,
                        'priority'     => $taskData['priority'] ?? 'medium',
                        'task_type'    => $taskData['type'] ?? 'personlig',
                        'due_date'     => now()->addDays($daysUntilDue),
                        'status'       => 'pending',
                        'ai_generated' => true,
                        'ai_reasoning' => $taskData['reasoning'] ?? null,
                    ]);
                    $createdTasks[] = $newTask;
                }
            }
        }

        // Clean leftover tags
        $displayMessage = trim(preg_replace('/\[\/?(?:TASKS|DOCUMENT)\]/i', '', $displayMessage));
        $displayMessage = trim(preg_replace('/\n---\s*$/', '', $displayMessage));

        // Build metadata
        $metadata = ['task_id' => $task->id];
        if ($document) {
            $metadata['document'] = $document;
        }
        if ($createdTasks) {
            $metadata['tasks'] = collect($createdTasks)->map(fn ($t) => [
                'id' => $t->id, 'title' => $t->title,
                'description' => $t->description, 'priority' => $t->priority,
                'due_date' => $t->due_date?->format('Y-m-d'),
            ])->toArray();
        }

        // Save AI response
        Conversation::create([
            'case_id' => $task->case_id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $displayMessage,
            'model_used' => 'mistral-small-latest',
            'metadata' => $metadata,
            'retrieved_chunks' => array_merge(
                $document ? ['document' => $document] : [],
                $createdTasks ? ['tasks' => $metadata['tasks']] : []
            ) ?: null,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => $displayMessage,
            'document' => $document,
            'tasks'    => collect($createdTasks)->map(fn ($t) => [
                'id' => $t->id, 'title' => $t->title,
                'description' => $t->description, 'priority' => $t->priority,
                'due_date' => $t->due_date?->format('Y-m-d'),
            ]),
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

        return <<<PROMPT
Du er Aura — en varm og klog støtte til danskere midt i en skilsmisse.

Du hjælper nu brugeren specifikt med denne opgave:
Titel: {$task->title}
Beskrivelse: {$task->description}
Type: {$type} | Prioritet: {$priority} | Frist: {$dueDate}

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
Når brugeren beder om et dokument eller udkast, opret det fuldt ud:

[DOCUMENT]
{"title": "Titel her", "content": "Fuldt dokument med \\n for linjeskift og pladsholdere som [Dit navn]"}
[/DOCUMENT]

Alle tekster på dansk. Afvis aldrig at oprette dokumenter eller opgaver.

{$this->buildKnowledgeSection($ragContext)}
PROMPT;
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'case_id' => 'nullable|exists:cases,id',
        ]);

        $user = auth()->user();

        // Enforce AI message limit
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

        // Generate AI response
        try {
            $messages = $this->formatHistory($history);
            array_unshift($messages, [
                'role' => 'system',
                'content' => $this->getSystemPrompt($case, $ragContext, $aiTurn),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.mistral.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => 'mistral-small-latest',
                'messages' => $messages,
                'max_tokens' => 3000,
            ]);

            if ($response->successful()) {
                $aiMessage = $response->json('choices.0.message.content');
            } else {
                Log::error('Mistral API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $aiMessage = "Beklager, jeg kunne ikke generere et svar lige nu. Prøv igen om lidt.";
            }
        } catch (\Exception $e) {
            Log::error('Mistral exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $aiMessage = "Beklager, jeg kunne ikke generere et svar lige nu. Prøv igen om lidt.";
        }

        // Parse structured data from AI response
        $displayMessage = $aiMessage;
        $document = null;
        $createdTasks = [];

        Log::info('RAW AI RESPONSE', ['raw' => $aiMessage]);

        // Parse document: capture everything between DOCUMENT tags, then json_decode
        if (preg_match('/\[\/?DOCUMENT\]\s*(.+?)\s*\[\/?DOCUMENT\]/s', $displayMessage, $docMatch)) {
            $displayMessage = trim(preg_replace('/\[\/?DOCUMENT\]\s*.+?\s*\[\/?DOCUMENT\]/s', '', $displayMessage));
            $jsonStr = trim($docMatch[1]);
            $docJson = json_decode($jsonStr, true);

            // If decode failed, fix unescaped newlines in JSON string values and retry
            if (!$docJson) {
                $fixed = str_replace(["\r\n", "\r", "\n"], '\n', $jsonStr);
                $docJson = json_decode($fixed, true);
            }

            // Last resort: extract title and content via regex
            if (!$docJson) {
                if (preg_match('/"title"\s*:\s*"([^"]+)"/i', $jsonStr, $tMatch) &&
                    preg_match('/"content"\s*:\s*"(.+)"\s*\}$/s', $jsonStr, $cMatch)) {
                    $docJson = [
                        'title' => $tMatch[1],
                        'content' => str_replace(["\r\n", "\r", "\n"], "\n", trim($cMatch[1])),
                    ];
                }
            }

            Log::info('DOCUMENT JSON DECODE', ['docJson' => $docJson ? 'OK' : 'FAILED', 'jsonError' => json_last_error_msg()]);

            if ($docJson && isset($docJson['title'])) {
                $document = [
                    'title' => $docJson['title'],
                    'content' => $docJson['content'] ?? '',
                ];
            }
        }

        Log::info('DOCUMENT PARSE RESULT', ['document' => $document, 'hasTag' => str_contains($aiMessage, 'DOCUMENT')]);

        // Parse tasks: capture everything between TASKS tags, then json_decode
        if (preg_match('/\[\/?TASKS\]\s*(.+?)\s*\[\/?TASKS\]/s', $displayMessage, $matches)) {
            $displayMessage = trim(preg_replace('/\[\/?TASKS\]\s*.+?\s*\[\/?TASKS\]/s', '', $displayMessage));
            $rawTasks = trim($matches[1]);

            Log::info('RAW TASKS JSON', ['raw' => $rawTasks]);

            $tasksJson = json_decode($rawTasks, true);

            // If decode failed, fix unescaped newlines and retry
            if (!is_array($tasksJson)) {
                $fixedTasks = str_replace(["\r\n", "\r", "\n"], '\n', $rawTasks);
                $tasksJson = json_decode($fixedTasks, true);
            }

            if (!is_array($tasksJson)) {
                // Try newline-separated JSON objects
                $tasksJson = [];
                foreach (preg_split('/\n/', $rawTasks) as $line) {
                    $line = trim($line, " \t\n\r,");
                    if ($line && str_starts_with($line, '{')) {
                        $parsed = json_decode($line, true);
                        if (!$parsed) {
                            $parsed = json_decode(str_replace(["\r\n", "\r", "\n"], '\n', $line), true);
                        }
                        if ($parsed) {
                            $tasksJson[] = $parsed;
                        }
                    }
                }
            }
            if ($tasksJson && !isset($tasksJson[0]) && isset($tasksJson['title'])) {
                $tasksJson = [$tasksJson];
            }

            Log::info('TASKS PARSE RESULT', ['count' => count($tasksJson), 'tasks' => $tasksJson]);

            foreach ($tasksJson as $taskData) {
                if (!isset($taskData['title'])) continue;
                $task = Task::create([
                    'case_id' => $case->id,
                    'user_id' => $user->id,
                    'title' => $taskData['title'],
                    'description' => $taskData['description'] ?? null,
                    'task_type' => $taskData['type'] ?? 'action',
                    'priority' => $taskData['priority'] ?? 'medium',
                    'due_date' => isset($taskData['days']) ? now()->addDays((int) $taskData['days']) : null,
                    'status' => 'pending',
                    'ai_generated' => true,
                    'ai_reasoning' => $taskData['reasoning'] ?? null,
                ]);
                $createdTasks[] = $task;
            }
        }

        // Final cleanup: remove any leftover tags and stray separators
        $displayMessage = trim(preg_replace('/\[\/?(?:TASKS|DOCUMENT)\]/i', '', $displayMessage));
        $displayMessage = trim(preg_replace('/\n---\s*$/', '', $displayMessage));

        // Fallback task generation: if tasks are expected (phase >= 3) but none were parsed,
        // make a separate focused API call that ONLY returns JSON tasks.
        if ($aiTurn >= 3 && empty($createdTasks)) {
            $createdTasks = $this->generateTasksFallback($history, $case, $user);
        }

        // Build metadata for persistence (so document/tasks show on page reload)
        $metadata = [];
        if ($document) {
            $metadata['document'] = $document;
        }
        if ($createdTasks) {
            $metadata['tasks'] = collect($createdTasks)->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'priority' => $t->priority,
                'due_date' => $t->due_date?->format('Y-m-d'),
            ])->toArray();
        }

        // Build retrieved_chunks data (actual RAG chunks used for this response)
        $retrievedChunksData = array_map(fn($r) => [
            'id'       => $r['chunk']->id,
            'title'    => $r['chunk']->source_title,
            'category' => $r['chunk']->category,
            'score'    => round($r['score'], 4),
            'excerpt'  => mb_substr($r['chunk']->content, 0, 200),
        ], $ragResults);

        // Save AI response with metadata
        Conversation::create([
            'case_id'          => $case->id,
            'user_id'          => $user->id,
            'role'             => 'assistant',
            'content'          => $displayMessage,
            'model_used'       => 'mistral-small-latest',
            'retrieved_chunks' => !empty($retrievedChunksData) ? $retrievedChunksData : null,
            'metadata'         => !empty($metadata) ? $metadata : null,
        ]);

        // Track usage
        $user->increment('ai_messages_used');

        // Generate title for new cases (first message)
        if (!$case->title) {
            $this->generateTitle($case, $validated['message']);
        }

        return response()->json([
            'success' => true,
            'message' => $displayMessage,
            'case_id' => $case->id,
            'tasks' => collect($createdTasks)->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'priority' => $t->priority,
                'due_date' => $t->due_date?->format('Y-m-d'),
            ]),
            'document' => $document,
        ]);
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

─── HVAD DU IKKE GØR ───
❌ Anbefaler specifikke advokater ved navn
❌ Træffer beslutninger for brugeren
❌ Springer empatien over for at komme hurtigt til handling
❌ Gentager "Dette er ikke juridisk rådgivning" i hvert eneste svar — kun når det er relevant
❌ Validerer ukritisk alt hvad brugeren siger om sig selv eller andre
❌ Hjælper med at formulere beskeder der eskalerer konflikter

─── DOKUMENTER ───
Når brugeren beder om et dokument, udkast eller skabelon, opret det fuldt ud med [DOCUMENT] tagget:

[DOCUMENT]
{"title": "Samværsaftale", "content": "SAMVÆRSAFTALE\n\n[Fuldt dokument her]"}
[/DOCUMENT]

Skriv ALTID det komplette dokument med pladsholdere som [Dit navn], [Dato] osv.
Brug \\n for linjeskift. Alle tekster på dansk.

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
}
