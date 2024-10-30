<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PostNoDebitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if($request->has('amount') && $request->user()->pnd == true)
        {
            return get_error_response(['error' => 'You have no debit account. Please contact your administrator.'], 400);
        }
        return $next($request);
    }
}
