<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                // Only expose what the frontend actually needs — never the full model
                'user' => $request->user() ? [
                    'id'                => $request->user()->id,
                    'name'              => $request->user()->name,
                    'email'             => $request->user()->email,
                    'phone'             => $request->user()->phone,
                    'email_verified_at' => $request->user()->email_verified_at,
                    'subscription_plan' => $request->user()->subscription_plan,
                    'wallet_balance'    => $request->user()->wallet_balance,
                    'ai_messages_used'  => $request->user()->ai_messages_used,
                    'ai_messages_limit' => $request->user()->ai_messages_limit,
                ] : null,
            ],
            'pendingTaskCount' => fn () => $request->user()
                ? $request->user()->tasks()->where('status', 'pending')->count()
                : 0,
            'newTaskCount' => fn () => $request->user()
                ? $request->user()->tasks()
                    ->where('status', 'pending')
                    ->where('created_at', '>=', now()->subDay())
                    ->count()
                : 0,
            'taskDueDates' => fn () => $request->user()
                ? $request->user()->tasks()
                    ->whereNotNull('due_date')
                    ->where('status', '!=', 'completed')
                    ->select('id', 'title', 'due_date', 'priority')
                    ->get()
                    ->map(fn ($t) => [
                        'id' => $t->id,
                        'title' => $t->title,
                        'due_date' => $t->due_date->format('Y-m-d'),
                        'priority' => $t->priority,
                    ])
                : [],
        ];
    }
}
