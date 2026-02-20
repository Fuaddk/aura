<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\AuraNotification;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Create a notification and broadcast it real-time.
     */
    public static function create(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null
    ): AuraNotification {
        $notification = AuraNotification::create([
            'user_id'    => $user->id,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'action_url' => $actionUrl,
        ]);

        try {
            broadcast(new NotificationCreated($notification));
        } catch (\Throwable $e) {
            // Broadcast fejl skal ikke stoppe applikationen
            \Illuminate\Support\Facades\Log::warning('Broadcast failed', ['error' => $e->getMessage()]);
        }

        return $notification;
    }

    /**
     * Notify user that Aura created new tasks.
     */
    public static function notifyNewTasks(User $user, array $tasks): void
    {
        if (empty($tasks)) return;

        $count = count($tasks);
        $titles = collect($tasks)
            ->take(3)
            ->pluck('title')
            ->join(', ');

        if ($count > 3) {
            $titles .= ' og ' . ($count - 3) . ' til';
        }

        self::create(
            $user,
            'new_tasks',
            $count === 1 ? '1 ny opgave oprettet' : "{$count} nye opgaver oprettet",
            "Aura har oprettet: {$titles}",
            route('tasks.index')
        );
    }

    /**
     * Notify user that their subscription was upgraded.
     */
    public static function notifySubscriptionUpgraded(User $user, string $plan): void
    {
        $planLabels = [
            'free'  => 'Gratis',
            'basis' => 'Basis',
            'pro'   => 'Pro',
        ];

        $planLabel = $planLabels[$plan] ?? ucfirst($plan);

        self::create(
            $user,
            'subscription_upgraded',
            'Plan opgraderet!',
            "Du er nu på {$planLabel}-planen. God fornøjelse med Aura!",
        );
    }

    /**
     * Notify user that their subscription was renewed.
     */
    public static function notifySubscriptionRenewed(User $user): void
    {
        self::create(
            $user,
            'subscription_renewed',
            'Abonnement fornyet',
            'Dit abonnement er automatisk fornyet. God fornøjelse med Aura!'
        );
    }

    /**
     * Check for tasks due in exactly 3 days and notify user.
     * Called daily by scheduler — includes duplicate protection.
     */
    public static function notifyUpcomingDeadlines(User $user): void
    {
        $targetDate = Carbon::today()->addDays(3)->toDateString();

        $tasks = $user->tasks()
            ->where('status', '!=', 'completed')
            ->whereDate('due_date', $targetDate)
            ->get();

        foreach ($tasks as $task) {
            // Undgå dubletter: tjek om notifikation allerede eksisterer for denne opgave i dag
            $alreadyNotified = AuraNotification::where('user_id', $user->id)
                ->where('type', 'deadline_soon')
                ->where('message', 'like', "%{$task->id}%")
                ->whereDate('created_at', Carbon::today())
                ->exists();

            if ($alreadyNotified) continue;

            $dueFormatted = Carbon::parse($task->due_date)->locale('da')->isoFormat('D. MMMM YYYY');

            self::create(
                $user,
                'deadline_soon',
                'Frist om 3 dage',
                "Opgaven '{$task->title}' (#{$task->id}) har frist {$dueFormatted}",
                route('chat.task', ['task' => $task->id])
            );
        }
    }
}
