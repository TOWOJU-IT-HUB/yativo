<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChargeUserWallet
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // if(auth()->check() && $request->has('amount') && $request->has('currency')) {
        //     $user = $request->user();
        //     $wallet = $user->wallet;
        // }
        return $next($request);
    }
}
