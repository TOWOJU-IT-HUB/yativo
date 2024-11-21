<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KycStatusMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if (($user->kyc_status != 'approved' or $user->is_kyc_submitted == false)) {
                return get_error_response(['error' => 'KYC is pending, please complete the KYC process to access this feature'], 403);
            }
            return $next($request);
        }

        return $next($request);
    }
}
