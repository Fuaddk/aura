<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresPlan
{
    /**
     * @param string ...$plans Allowed plans, e.g. requires_plan:basis,pro,business
     */
    public function handle(Request $request, Closure $next, string ...$plans): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->subscription_plan, $plans)) {
            return redirect()->route('dashboard')
                ->with('plan_required', implode(',', $plans));
        }

        return $next($request);
    }
}
