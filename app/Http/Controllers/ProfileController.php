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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
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

        $tokensUsed  = $user->ai_tokens_used  ?? 0;
        $tokensLimit = $user->ai_tokens_limit ?? 100000;
        $usagePercent  = $tokensLimit > 0
            ? min(100, round(($tokensUsed / $tokensLimit) * 100))
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
            'tokensUsed'        => $tokensUsed,
            'tokensLimit'       => $tokensLimit,
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
            'plan' => ['required', 'in:free,basis,pro'],
        ]);

        $plan  = \App\Models\SubscriptionPlan::where('slug', $validated['plan'])->first();
        $limit = $plan ? ($plan->tokens_limit === 0 ? 9999999 : $plan->tokens_limit) : 100000;

        $user = $request->user();
        $user->update([
            'subscription_plan' => $validated['plan'],
            'ai_tokens_limit'   => $limit,
        ]);
        Cache::forget("user:{$user->id}:plan_features");

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
     * Gem brugerens foretrukne AI-model.
     */
    public function updatePreferredModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', Rule::in(['mistral-small-latest', 'mistral-large-latest'])],
        ]);

        $user = $request->user();

        // Gratis plan låses altid til small-model
        if ($user->subscription_plan === 'free') {
            $validated['model'] = 'mistral-small-latest';
        }

        $user->update(['preferred_model' => $validated['model']]);

        return response()->json(['model' => $user->preferred_model]);
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
