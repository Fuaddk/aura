<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});


Route::middleware(['auth', 'verified'])->group(function () {
    // Chat er main dashboard
    Route::get('/dashboard', [ChatController::class, 'index'])->name('dashboard');

    // AI-intensive endpoints - stricter rate limiting
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
        Route::post('/chat/upload', [ChatController::class, 'uploadDocument'])->name('chat.upload');
        Route::post('/chat/task/{task}/send', [ChatController::class, 'taskChatSend'])->name('chat.task.send');
        Route::post('/chat/task/{task}/upload', [ChatController::class, 'taskChatUpload'])->name('chat.task.upload');
    });

    // General API endpoints - moderate rate limiting
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('/chat/task/{task}', [ChatController::class, 'taskChat'])->name('chat.task');
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::post('/tasks/{task}/documents', [TaskController::class, 'saveDocument'])->name('tasks.documents.save');
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::middleware('requires_feature:calendar')->group(function () {
            Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
            Route::get('/calendar/ics', [CalendarController::class, 'ics'])->name('calendar.ics');
        });
        Route::middleware('requires_feature:inbox')->group(function () {
            Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
            Route::post('/inbox/connect', [InboxController::class, 'connect'])->name('inbox.connect');
            Route::delete('/inbox/accounts/{account}', [InboxController::class, 'disconnect'])->name('inbox.disconnect');
            Route::post('/inbox/accounts/{account}/sync', [InboxController::class, 'sync'])->name('inbox.sync');
            Route::patch('/inbox/accounts/{account}/auto-sync', [InboxController::class, 'toggleAutoSync'])->name('inbox.auto-sync');
        });
        Route::delete('/cases/{case}', [ChatController::class, 'destroyCase'])->name('cases.destroy');
        Route::delete('/cases/period/{period}', [ChatController::class, 'destroyPeriod'])->name('cases.destroy.period');
        // Notifications
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/extra-usage', [ProfileController::class, 'updateExtraUsage'])->name('profile.extra-usage');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Sensitive profile operations — strict rate limiting
    Route::middleware('throttle:5,1')->group(function () {
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

    // Two-Factor Authentication — strict rate limiting
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
        Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
        Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
    });

    // Stripe subscription — moderate rate limiting
    Route::middleware('throttle:10,1')->group(function () {
        Route::get('/subscription/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');
        Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
        Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');
        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::get('/subscription/portal', [SubscriptionController::class, 'portal'])->name('subscription.portal');
        Route::post('/subscription/wallet/topup', [SubscriptionController::class, 'walletTopup'])->name('subscription.wallet.topup');
        Route::get('/subscription/wallet/success', [SubscriptionController::class, 'walletSuccess'])->name('subscription.wallet.success');
    });
});

// Admin panel
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
    Route::patch('/users/{user}/plan', [AdminController::class, 'updatePlan'])->name('users.plan');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
    Route::post('/notifications', [AdminController::class, 'sendNotification'])->name('notifications.send');

    // Brugerchat
    Route::get('/users/{user}/conversations', [AdminController::class, 'userConversations'])->name('users.conversations');

    // Vidensbase (RAG)
    Route::post('/knowledge/index-predefined', [AdminController::class, 'indexPredefinedSource'])->name('knowledge.index-predefined');
    Route::post('/knowledge/url', [AdminController::class, 'addKnowledgeUrl'])->name('knowledge.url');
    Route::post('/knowledge/document', [AdminController::class, 'uploadKnowledgeDocument'])->name('knowledge.document');
    Route::delete('/knowledge/source', [AdminController::class, 'deleteKnowledgeSource'])->name('knowledge.source.destroy');

    // API-indstillinger
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::patch('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    Route::patch('/settings/extra-usage-rate', [AdminController::class, 'updateExtraUsageRate'])->name('settings.extra-usage-rate');

    // Subscription plan management
    Route::post('/subscription-plans', [AdminController::class, 'storePlan'])->name('subscription-plans.store');
    Route::patch('/subscription-plans/{plan}', [AdminController::class, 'updateSubscriptionPlan'])->name('subscription-plans.update');
    Route::delete('/subscription-plans/{plan}', [AdminController::class, 'destroySubscriptionPlan'])->name('subscription-plans.destroy');
});

// Stripe webhook – undtaget CSRF
Route::post('/stripe/webhook', [SubscriptionController::class, 'webhook'])->name('cashier.webhook');

require __DIR__.'/auth.php';