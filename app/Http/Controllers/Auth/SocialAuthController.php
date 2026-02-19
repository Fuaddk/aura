<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $socialUser = Socialite::driver('google')->user();

        // Find existing user by google_id or email
        $user = User::where('google_id', $socialUser->getId())->first()
             ?? User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            // Link google_id to existing account if not already linked
            if (!$user->google_id) {
                $user->update(['google_id' => $socialUser->getId()]);
            }
        } else {
            // Create new user from Google account
            $user = User::create([
                'name'              => $socialUser->getName(),
                'email'             => $socialUser->getEmail(),
                'google_id'         => $socialUser->getId(),
                'password'          => null,
                'email_verified_at' => now(),
                'subscription_plan' => 'free',
                'ai_tokens_limit' => 100000,
            ]);
        }

        // Respect 2FA if enabled
        if ($user->hasTwoFactorEnabled()) {
            session([
                'two_factor:user_id' => $user->id,
                'two_factor:remember' => false,
            ]);
            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, remember: true);
        return redirect()->intended(route('dashboard'));
    }
}
