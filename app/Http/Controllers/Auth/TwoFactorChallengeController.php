<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        if (!$request->session()->has('two_factor:user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'          => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $userId = $request->session()->get('two_factor:user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);
        $valid = false;

        if ($code = $request->code) {
            $valid = app('pragmarx.google2fa')->verifyKey(
                $user->two_factor_secret,
                $code
            );
        } elseif ($recoveryCode = $request->recovery_code) {
            $valid = $this->verifyRecoveryCode($user, $recoveryCode);
        } else {
            return back()->withErrors(['code' => 'Angiv en bekræftelseskode.']);
        }

        if (!$valid) {
            return back()->withErrors(['code' => 'Ugyldig kode. Prøv igen.']);
        }

        Auth::login($user, $request->session()->get('two_factor:remember', false));

        $request->session()->forget('two_factor:user_id');
        $request->session()->forget('two_factor:remember');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = json_decode($user->two_factor_recovery_codes, true);
        if (!is_array($codes)) {
            return false;
        }

        foreach ($codes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                // Remove used code
                unset($codes[$index]);
                $user->two_factor_recovery_codes = json_encode(array_values($codes));
                $user->save();
                return true;
            }
        }

        return false;
    }
}
