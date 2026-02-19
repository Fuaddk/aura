<?php

namespace App\Http\Middleware;

use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $user = $request->user();
        $uid  = $user?->id;

        return [
            ...parent::share($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'display_name'      => $user->display_name,
                    'work_description'  => $user->work_description,
                    'preferences'       => $user->preferences,
                    'email'             => $user->email,
                    'phone'             => $user->phone,
                    'is_admin'          => $user->is_admin,
                    'email_verified_at' => $user->email_verified_at,
                    'subscription_plan' => $user->subscription_plan,
                    'wallet_balance'    => $user->wallet_balance,
                    'ai_messages_used'      => $user->ai_messages_used,
                    'ai_messages_limit'     => $user->ai_messages_limit,
                    'extra_usage_enabled'   => $user->extra_usage_enabled,
                    'auto_refill_enabled'   => $user->auto_refill_enabled,
                    'auto_refill_threshold' => $user->auto_refill_threshold,
                    'auto_refill_amount'    => $user->auto_refill_amount,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                ] : null,
            ],
            'pendingTaskCount' => fn () => $uid
                ? Cache::remember("user:{$uid}:pending_tasks", 120, fn () =>
                    $user->tasks()->where('status', '!=', 'completed')->count()
                ) : 0,
            'newTaskCount' => fn () => $uid
                ? Cache::remember("user:{$uid}:new_tasks", 120, fn () =>
                    $user->tasks()
                        ->where('status', '!=', 'completed')
                        ->where('created_at', '>=', now()->subDay())
                        ->count()
                ) : 0,
            'urgentTaskCount' => fn () => $uid
                ? Cache::remember("user:{$uid}:urgent_tasks", 120, fn () =>
                    $user->tasks()
                        ->where('status', '!=', 'completed')
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<=', now()->addDays(3))
                        ->count()
                ) : 0,
            'warningTaskCount' => fn () => $uid
                ? Cache::remember("user:{$uid}:warning_tasks", 120, fn () =>
                    $user->tasks()
                        ->where('status', '!=', 'completed')
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '>', now()->addDays(3))
                        ->whereDate('due_date', '<=', now()->addDays(7))
                        ->count()
                ) : 0,
            'soonTaskCount' => fn () => $uid
                ? Cache::remember("user:{$uid}:soon_tasks", 120, fn () =>
                    $user->tasks()
                        ->where('status', '!=', 'completed')
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '>', now()->addDays(7))
                        ->whereDate('due_date', '<=', now()->addDays(14))
                        ->count()
                ) : 0,
            'connectedEmailCount' => fn () => $uid
                ? Cache::remember("user:{$uid}:email_count", 300, fn () =>
                    EmailAccount::where('user_id', $uid)->where('is_active', true)->count()
                ) : 0,
            'taskDueDates' => fn () => $uid
                ? Cache::remember("user:{$uid}:task_due_dates", 120, fn () =>
                    $user->tasks()
                        ->whereNotNull('due_date')
                        ->where('status', '!=', 'completed')
                        ->select('id', 'title', 'due_date', 'priority')
                        ->get()
                        ->map(fn ($t) => [
                            'id'       => $t->id,
                            'title'    => $t->title,
                            'due_date' => $t->due_date->format('Y-m-d'),
                            'priority' => $t->priority,
                        ])
                ) : [],
            'notifications' => fn () => $uid
                ? \App\Models\AuraNotification::where('user_id', $uid)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'type', 'title', 'message', 'is_read', 'action_url', 'created_at'])
                : [],
            'unreadNotificationCount' => fn () => $uid
                ? \App\Models\AuraNotification::where('user_id', $uid)
                    ->where('is_read', false)
                    ->count()
                : 0,
        ];
    }

    /**
     * Flush task-related caches for a user (call after task create/update/delete).
     */
    public static function flushTaskCache(int $userId): void
    {
        $keys = ['pending_tasks', 'new_tasks', 'urgent_tasks', 'warning_tasks', 'soon_tasks', 'task_due_dates'];
        foreach ($keys as $key) {
            Cache::forget("user:{$userId}:{$key}");
        }
    }
}
