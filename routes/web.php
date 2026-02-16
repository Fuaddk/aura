<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Chat er main dashboard
    Route::get('/dashboard', [ChatController::class, 'index'])->name('dashboard');
    Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/chat/task/{task}', [ChatController::class, 'taskChat'])->name('chat.task');
    Route::post('/chat/task/{task}/send', [ChatController::class, 'taskChatSend'])->name('chat.task.send');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::post('/tasks/{task}/documents', [TaskController::class, 'saveDocument'])->name('tasks.documents.save');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::delete('/cases/{case}', [ChatController::class, 'destroyCase'])->name('cases.destroy');
    Route::delete('/cases/period/{period}', [ChatController::class, 'destroyPeriod'])->name('cases.destroy.period');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Stripe subscription
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::get('/subscription/portal', [SubscriptionController::class, 'portal'])->name('subscription.portal');
});

// Stripe webhook – undtaget CSRF
Route::post('/stripe/webhook', [SubscriptionController::class, 'webhook'])->name('cashier.webhook');

require __DIR__.'/auth.php';