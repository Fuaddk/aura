<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\CaseModel;
use App\Models\SubscriptionPlan;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $messagesUsed  = $user->ai_messages_used  ?? 0;
        $messagesLimit = $user->ai_messages_limit  ?? 50;
        $usagePercent  = $messagesLimit > 0
            ? min(100, round(($messagesUsed / $messagesLimit) * 100))
            : 0;

        $cases = CaseModel::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail'   => $user instanceof MustVerifyEmail,
            'status'            => session('status'),
            'topupAmount'       => session('topup_amount'),
            'twoFactor'         => session('twoFactor'),
            'usagePercent'      => $usagePercent,
            'messagesUsed'      => $messagesUsed,
            'messagesLimit'     => $messagesLimit,
            'casesCount'        => $cases->count(),
            'tasksCount'        => $user->tasks()->count(),
            'cases'             => $cases,
            'subscriptionPlans' => SubscriptionPlan::orderBy('sort_order')->where('is_active', true)->get(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password'      => ['required', 'current_password'],
            'password'              => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return Redirect::route('profile.edit')->with('status', 'password-updated');
    }

    /**
     * Update the user's subscription plan.
     */
    public function updateSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'in:free,pro,business'],
        ]);

        $limits = [
            'free'     => 50,
            'pro'      => 500,
            'business' => 999999,
        ];

        $request->user()->update([
            'subscription_plan' => $validated['plan'],
            'ai_messages_limit' => $limits[$validated['plan']],
        ]);

        return Redirect::route('profile.edit')->with('status', 'subscription-updated');
    }

    /**
     * Update extra usage / auto-refill settings.
     */
    public function updateExtraUsage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'extra_usage_enabled'  => 'required|boolean',
            'auto_refill_enabled'  => 'boolean',
            'auto_refill_threshold' => 'integer|min:10|max:1000',
            'auto_refill_amount'   => 'integer|min:10|max:5000',
        ]);

        $request->user()->update($validated);

        return response()->json(['ok' => true]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
