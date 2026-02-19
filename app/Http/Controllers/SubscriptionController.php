<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController extends Controller
{
    private function limitForPlan(string $slug): int
    {
        $plan = SubscriptionPlan::where('slug', $slug)->first();
        if (!$plan) return 50;
        return $plan->messages_limit === 0 ? 999999 : $plan->messages_limit;
    }

    public function plans(): Response
    {
        $plans = SubscriptionPlan::orderBy('sort_order')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Subscription/Plans', ['subscriptionPlans' => $plans]);
    }

    /**
     * Redirect til Stripe Checkout for at oprette eller skifte abonnement.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string'],
        ]);

        $plan     = $validated['plan'];
        $user     = $request->user();
        $planModel = SubscriptionPlan::where('slug', $plan)
            ->where('is_active', true)
            ->whereNotNull('stripe_price_id')
            ->first();

        if (!$planModel) {
            return back()->withErrors(['plan' => 'Ugyldig plan eller Stripe Price ID mangler for denne plan.']);
        }

        $price = $planModel->stripe_price_id;
        $limit = $planModel->messages_limit === 0 ? 999999 : $planModel->messages_limit;

        try {
            // If already subscribed, swap plan. Otherwise create new subscription.
            if ($user->subscribed('default')) {
                $user->subscription('default')->swap($price);

                $user->update([
                    'subscription_plan' => $plan,
                    'ai_messages_limit' => $limit,
                ]);

                return redirect()->route('profile.edit', ['section' => 'subscription'])
                    ->with('status', 'subscription-updated');
            }

            // New subscription → Stripe Checkout
            return $user->newSubscription('default', $price)
                ->allowPromotionCodes()
                ->checkout([
                    'success_url' => route('subscription.success') . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => route('profile.edit') . '#subscription',
                    'metadata'    => ['plan' => $plan],
                    'locale'      => 'da',
                ]);

        } catch (IncompletePayment $e) {
            return redirect()->route('cashier.payment', [$e->payment->id, 'redirect' => route('profile.edit')]);
        }
    }

    /**
     * Håndter succesfuld Stripe Checkout.
     */
    public function success(Request $request): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        $user      = $request->user();

        if ($sessionId) {
            try {
                $session = \Laravel\Cashier\Cashier::stripe()->checkout->sessions->retrieve($sessionId);
                $plan    = $session->metadata->plan ?? null;

                // Only activate plan if payment actually succeeded
                if ($plan && $session->payment_status === 'paid') {
                    $user->update([
                        'subscription_plan' => $plan,
                        'ai_messages_limit' => $this->limitForPlan($plan),
                    ]);

                    \App\Services\NotificationService::notifySubscriptionUpgraded($user, $plan);

                    return redirect()->route('profile.edit', ['section' => 'subscription'])
                        ->with('status', 'subscription-updated');
                }

                // Payment not confirmed — redirect with error
                Log::warning('Stripe checkout reached success URL but payment_status is not paid', [
                    'user_id'        => $user->id,
                    'payment_status' => $session->payment_status ?? 'unknown',
                    'session_id'     => $sessionId,
                ]);

                return redirect()->route('profile.edit', ['section' => 'subscription'])
                    ->with('status', 'subscription-payment-failed');

            } catch (\Exception $e) {
                Log::error('Stripe checkout success error', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('profile.edit', ['section' => 'subscription'])
            ->with('status', 'subscription-updated');
    }

    /**
     * Redirect til Stripe Customer Portal (fakturaer, opsigelse, betalingsmetode).
     */
    public function portal(Request $request): RedirectResponse
    {
        return $request->user()->redirectToBillingPortal(
            route('profile.edit') . '#subscription'
        );
    }

    /**
     * Skift til gratis plan (annuller Stripe abonnement).
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->subscribed('default')) {
            $user->subscription('default')->cancel();
        }

        $user->update([
            'subscription_plan' => 'free',
            'ai_messages_limit' => $this->limitForPlan('free'),
        ]);

        return redirect()->route('profile.edit', ['section' => 'subscription'])
            ->with('status', 'subscription-cancelled');
    }

    /**
     * Stripe Webhook — synkroniser abonnementsstatus automatisk.
     * Route er undtaget CSRF via VerifyCsrfToken.
     */
    public function webhook(Request $request)
    {
        // Detect renewal events before delegating to Cashier
        try {
            $payload       = json_decode($request->getContent(), true);
            $eventType     = $payload['type'] ?? null;
            $billingReason = $payload['data']['object']['billing_reason'] ?? null;

            if ($eventType === 'invoice.payment_succeeded' && $billingReason === 'subscription_cycle') {
                $customerId = $payload['data']['object']['customer'] ?? null;
                if ($customerId) {
                    $user = \App\Models\User::where('stripe_id', $customerId)->first();
                    if ($user) {
                        \App\Services\NotificationService::notifySubscriptionRenewed($user);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Webhook notification error', ['error' => $e->getMessage()]);
        }

        // Laravel Cashier's built-in webhook handler
        return app(\Laravel\Cashier\Http\Controllers\WebhookController::class)
            ->handleWebhook($request);
    }
}
