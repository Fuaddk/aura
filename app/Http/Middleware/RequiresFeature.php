<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresFeature
{
    /**
     * @param string $feature  Feature key, e.g. 'calendar' or 'inbox'
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user) {
            $plan = \App\Models\SubscriptionPlan::where('slug', $user->subscription_plan)->first();
            if ($plan && ($plan->feature_flags[$feature] ?? false)) {
                return $next($request);
            }
        }

        return redirect()->route('dashboard')->with('feature_required', $feature);
    }
}
