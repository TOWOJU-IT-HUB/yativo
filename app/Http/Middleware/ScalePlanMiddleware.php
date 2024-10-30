<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScalePlanMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // if (auth()->check()) {
        //     $user = request()->user();
        //     $subscription = $user->activeSubscription();
        //     $plan = [2, 3]; // 2 -> scale | 3 -> Enterprise
        //     if (in_array($subscription->plan_id, $plan)) {
                return $next($request);
        //     }
        // }

        // return get_error_response(['error' => "Please upgrade to a supported plan to use this endpoint"]);
    }
}
