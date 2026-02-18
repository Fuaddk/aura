<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-sync alle aktive mailkonti hvert 30. minut
Schedule::command('inbox:sync')->everyThirtyMinutes()->withoutOverlapping();

// Daglige frist-notifikationer kl. 08:00
Schedule::call(function () {
    $users = \App\Models\User::whereHas('tasks', fn ($q) =>
        $q->where('status', '!=', 'completed')->whereNotNull('due_date')
    )->get();

    foreach ($users as $user) {
        \App\Services\NotificationService::notifyUpcomingDeadlines($user);
    }
})->dailyAt('08:00')->name('notify-upcoming-deadlines')->withoutOverlapping();
