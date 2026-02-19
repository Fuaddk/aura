<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    public function enable(Request $request)
    {
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user = $request->user();
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $writer = new \BaconQrCode\Writer($renderer);
        $svg = $writer->writeString($qrUrl);

        return back()->with('twoFactor', [
            'qrCodeSvg' => $svg,
            'secret'    => $secret,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $google2fa = app('pragmarx.google2fa');
        $user = $request->user();

        if (!$user->two_factor_secret || $user->two_factor_confirmed_at) {
            return back()->withErrors(['code' => 'Totrinsgodkendelse er ikke klar til bekræftelse.']);
        }

        if (!$google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            // Re-flash the QR code so the form stays visible after the failed attempt
            $qrUrl = $google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $user->two_factor_secret
            );
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $writer = new \BaconQrCode\Writer($renderer);
            $svg = $writer->writeString($qrUrl);

            return back()
                ->with('twoFactor', ['qrCodeSvg' => $svg, 'secret' => $user->two_factor_secret])
                ->withErrors(['code' => 'Ugyldig bekræftelseskode. Prøv igen.']);
        }

        $codes = Collection::times(8, fn () => Str::random(10));

        $user->two_factor_recovery_codes = json_encode(
            $codes->map(fn ($code) => Hash::make($code))->all()
        );
        $user->two_factor_confirmed_at = now();
        $user->save();

        return back()->with('twoFactor', [
            'recoveryCodes' => $codes->all(),
        ]);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = $request->user();
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return back()->with('status', 'two-factor-disabled');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return back()->withErrors(['password' => 'Totrinsgodkendelse er ikke aktiveret.']);
        }

        $codes = Collection::times(8, fn () => Str::random(10));

        $user->two_factor_recovery_codes = json_encode(
            $codes->map(fn ($code) => Hash::make($code))->all()
        );
        $user->save();

        return back()->with('twoFactor', [
            'recoveryCodes' => $codes->all(),
        ]);
    }
}
