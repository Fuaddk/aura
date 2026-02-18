<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\CaseModel;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\KnowledgeChunk;
use App\Models\SubscriptionPlan;
use App\Models\Task;
use App\Models\User;
use App\Services\KnowledgeService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    private function baseProps(): array
    {
        $stats = [
            'total_users'         => User::count(),
            'users_today'         => User::whereDate('created_at', today())->count(),
            'total_conversations' => CaseModel::count(),
            'total_documents'     => Document::count(),
            'total_tasks'         => Task::count(),
            'ai_messages_total'   => (int) User::sum('ai_messages_used'),
        ];

        $plans = User::selectRaw('subscription_plan, count(*) as count')
            ->groupBy('subscription_plan')
            ->pluck('count', 'subscription_plan')
            ->toArray();

        foreach (['free', 'pro', 'business'] as $plan) {
            $plans[$plan] = $plans[$plan] ?? 0;
        }

        $users = User::orderByDesc('created_at')
            ->select('id', 'name', 'email', 'is_admin', 'subscription_plan',
                     'ai_messages_used', 'ai_messages_limit', 'created_at', 'google_id')
            ->paginate(50);

        $knowledgeSources = KnowledgeChunk::selectRaw(
            'source_url, source_title, category, count(*) as chunks, max(scraped_at) as scraped_at'
        )->groupBy('source_url', 'source_title', 'category')
         ->orderByDesc('scraped_at')
         ->get();

        // Hardcoded predefined sources with indexed status
        $indexedUrls = $knowledgeSources->pluck('source_url')->toArray();
        $predefinedSources = collect(KnowledgeService::getSources())->map(function ($src) use ($indexedUrls) {
            $src['indexed'] = in_array($src['url'], $indexedUrls);
            return $src;
        })->values();

        // Check actual .env/config values so indicators reflect real connectivity
        $fn = fn($val, $secret) => [
            'is_set'   => !empty($val),
            'is_secret'=> $secret,
            'preview'  => $val ? ($secret ? substr($val, 0, 8) . '...' : $val) : null,
        ];
        $appSettings = collect([
            'stripe_key'            => $fn(config('cashier.key'),                    false),
            'stripe_secret'         => $fn(config('cashier.secret'),                 true),
            'stripe_webhook_secret' => $fn(config('cashier.webhook.secret'),         true),
            'google_client_id'      => $fn(config('services.google.client_id'),      false),
            'google_client_secret'  => $fn(config('services.google.client_secret'),  true),
            'openai_api_key'        => $fn(config('services.openai.key'),            true),
            'anthropic_api_key'     => $fn(config('services.anthropic.key'),         true),
            'mistral_api_key'       => $fn(config('services.mistral.key'),           true),
        ]);

        $subscriptionPlans = SubscriptionPlan::orderBy('sort_order')->get();

        return [
            'stats'              => $stats,
            'plans'              => $plans,
            'users'              => $users,
            'knowledgeSources'   => $knowledgeSources,
            'predefinedSources'  => $predefinedSources,
            'appSettings'        => $appSettings,
            'subscriptionPlans'  => $subscriptionPlans,
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', $this->baseProps());
    }

    public function updatePlan(Request $request, User $user): RedirectResponse
    {
        $request->validate(['plan' => 'required|in:free,pro,business']);

        $limits = ['free' => 50, 'pro' => 500, 'business' => 99999];

        $user->update([
            'subscription_plan' => $request->plan,
            'ai_messages_limit' => $limits[$request->plan],
        ]);

        return back()->with('success', "Plan opdateret til {$request->plan}.");
    }

    public function destroyUser(User $user): RedirectResponse
    {
        abort_if($user->is_admin, 403, 'Kan ikke slette en admin-bruger.');
        abort_if($user->id === auth()->id(), 403, 'Kan ikke slette dig selv.');

        $user->delete();

        return back()->with('success', 'Bruger slettet.');
    }

    public function sendNotification(Request $request): RedirectResponse
    {
        $request->validate([
            'target'  => 'required|in:all,user',
            'user_id' => 'nullable|exists:users,id',
            'title'   => 'required|max:120',
            'message' => 'required|max:500',
        ]);

        if ($request->target === 'all') {
            User::chunk(100, function ($users) use ($request) {
                foreach ($users as $user) {
                    NotificationService::create($user, 'admin', $request->title, $request->message);
                }
            });

            return back()->with('success', 'Notifikation sendt til alle brugere.');
        }

        $user = User::findOrFail($request->user_id);
        NotificationService::create($user, 'admin', $request->title, $request->message);

        return back()->with('success', "Notifikation sendt til {$user->name}.");
    }

    /* ── Vidensbase (RAG) ──────────────────────────────────── */

    public function indexPredefinedSource(Request $request): RedirectResponse
    {
        $request->validate(['url' => 'required|url', 'title' => 'required|string', 'category' => 'required|string']);

        $service = new KnowledgeService();
        $count = $service->processSource([
            'url'      => $request->url,
            'title'    => $request->title,
            'category' => $request->category,
        ]);

        if ($count > 0) {
            return back()->with('success', "{$count} chunks indekseret fra \"{$request->title}\".");
        }

        $alreadyExists = \App\Models\KnowledgeChunk::where('source_url', $request->url)->exists();
        if ($alreadyExists) {
            return back()->with('success', "\"{$request->title}\" er allerede indekseret – ingen ændringer.");
        }

        return back()->with('error', "Scraping fejlede for \"{$request->title}\". Siden blokerer muligvis bots. Prøv at uploade indholdet som .txt-fil i stedet.");
    }

    public function addKnowledgeUrl(Request $request): RedirectResponse
    {
        $request->validate([
            'url'      => 'required|url|max:500',
            'title'    => 'required|string|max:200',
            'category' => 'required|string|max:100',
        ]);

        $service = new KnowledgeService();
        $count = $service->processSource([
            'url'      => $request->url,
            'title'    => $request->title,
            'category' => $request->category,
        ]);

        if ($count > 0) {
            return back()->with('success', "{$count} chunks tilføjet fra URL.");
        }

        $alreadyExists = \App\Models\KnowledgeChunk::where('source_url', $request->url)->exists();
        if ($alreadyExists) {
            return back()->with('success', "URL er allerede indekseret – ingen ændringer.");
        }

        return back()->with('error', "Scraping fejlede – siden blokerer muligvis bots eller indeholder ingen brugbar tekst. Prøv at uploade indholdet som .txt-fil.");
    }

    public function uploadKnowledgeDocument(Request $request): RedirectResponse
    {
        $request->validate([
            'file'     => 'required|file|mimes:txt|max:10240',
            'title'    => 'required|string|max:200',
            'category' => 'required|string|max:100',
        ]);

        $text = file_get_contents($request->file('file')->getRealPath());
        $filename = $request->file('file')->getClientOriginalName();
        $sourceUrl = 'upload:/' . $filename;

        $service = new KnowledgeService();
        $chunks = $service->chunkText($text);

        if (empty($chunks)) {
            return back()->with('error', 'Dokumentet indeholder ingen brugbar tekst.');
        }

        $embeddings = $service->createEmbeddings(array_values($chunks));

        foreach ($chunks as $i => $chunkText) {
            KnowledgeChunk::create([
                'source_url'   => $sourceUrl,
                'source_title' => $request->title,
                'content'      => $chunkText,
                'embedding'    => $embeddings[$i] ?? null,
                'category'     => $request->category,
                'chunk_index'  => $i,
                'token_count'  => $service->estimateTokens($chunkText),
                'content_hash' => hash('sha256', $chunkText),
                'scraped_at'   => now(),
            ]);
        }

        return back()->with('success', count($chunks) . ' chunks gemt fra dokument.');
    }

    public function deleteKnowledgeSource(Request $request): RedirectResponse
    {
        $request->validate(['source_url' => 'required|string']);

        $count = KnowledgeChunk::where('source_url', $request->source_url)->delete();

        return back()->with('success', "{$count} chunks slettet.");
    }

    /* ── Brugerchat ─────────────────────────────────────────── */

    public function userConversations(User $user): Response
    {
        $cases = CaseModel::where('user_id', $user->id)
            ->with(['conversations' => function ($q) {
                $q->orderBy('id')
                  ->select('id', 'case_id', 'role', 'content', 'model_used', 'created_at');
            }])
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'status', 'created_at']);

        return Inertia::render('Admin/Dashboard', array_merge($this->baseProps(), [
            'viewUser'  => $user->only('id', 'name', 'email'),
            'userCases' => $cases,
        ]));
    }

    /* ── API-indstillinger ──────────────────────────────────── */

    public function settings(): Response
    {
        return Inertia::render('Admin/Dashboard', $this->baseProps());
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $envMap = [
            'stripe_key'            => 'STRIPE_KEY',
            'stripe_secret'         => 'STRIPE_SECRET',
            'stripe_webhook_secret' => 'STRIPE_WEBHOOK_SECRET',
            'google_client_id'      => 'GOOGLE_CLIENT_ID',
            'google_client_secret'  => 'GOOGLE_CLIENT_SECRET',
            'openai_api_key'        => 'OPENAI_API_KEY',
            'anthropic_api_key'     => 'ANTHROPIC_API_KEY',
            'mistral_api_key'       => 'MISTRAL_API_KEY',
        ];

        $request->validate(
            collect(array_keys($envMap))->mapWithKeys(fn($k) => [$k => 'nullable|string|max:500'])->toArray()
        );

        $updated = 0;
        foreach ($envMap as $formKey => $envKey) {
            $value = $request->input($formKey);
            if ($value !== null && $value !== '') {
                $this->setEnvValue($envKey, $value);
                $updated++;
            }
        }

        if ($updated > 0) {
            \Artisan::call('config:clear');
        }

        return back()->with('success', $updated > 0 ? "{$updated} nøgle(r) opdateret i .env." : 'Ingen ændringer — alle felter var tomme.');
    }

    /* ── Subscription plan management ──────────────────────── */

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slug'            => 'required|string|max:50|unique:subscription_plans,slug|regex:/^[a-z0-9\-]+$/',
            'name'            => 'required|string|max:100',
            'description'     => 'nullable|string|max:300',
            'price'           => 'required|integer|min:0',
            'messages_limit'  => 'required|integer|min:0',
            'features'        => 'nullable|string',
            'stripe_price_id' => 'nullable|string|max:200',
            'color'           => 'nullable|string|max:20',
            'is_popular'      => 'boolean',
            'is_active'       => 'boolean',
            'sort_order'      => 'integer|min:0',
        ]);

        $data['features'] = $this->parseFeatures($data['features'] ?? '');

        SubscriptionPlan::create($data);

        return back()->with('success', "Plan \"{$data['name']}\" oprettet.");
    }

    public function updateSubscriptionPlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'description'     => 'nullable|string|max:300',
            'price'           => 'required|integer|min:0',
            'messages_limit'  => 'required|integer|min:0',
            'features'        => 'nullable|string',
            'stripe_price_id' => 'nullable|string|max:200',
            'color'           => 'nullable|string|max:20',
            'is_popular'      => 'boolean',
            'is_active'       => 'boolean',
            'sort_order'      => 'integer|min:0',
        ]);

        $data['features'] = $this->parseFeatures($data['features'] ?? '');

        $plan->update($data);

        return back()->with('success', "Plan \"{$plan->name}\" opdateret.");
    }

    public function destroySubscriptionPlan(SubscriptionPlan $plan): RedirectResponse
    {
        $plan->delete();
        return back()->with('success', "Plan \"{$plan->name}\" slettet.");
    }

    private function parseFeatures(string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $raw))
        ));
    }

    private function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        // Quote value if it contains spaces or special characters
        $safe = preg_match('/\s/', $value) ? '"' . addslashes($value) . '"' : $value;

        if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
            $content = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $key . '=' . $safe, $content);
        } else {
            $content .= "\n" . $key . '=' . $safe;
        }

        file_put_contents($envPath, $content);
    }
}
