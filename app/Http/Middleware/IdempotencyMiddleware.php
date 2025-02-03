<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the request method is POST and no idempotency key is provided
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && !$request->header('Idempotency-Key')) {
            return get_error_response(['error' => 'Idempotency key missing'], 400);
        }

        // Check if the idempotency key exists in the cache
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey && Cache::has($idempotencyKey)) {
            return Cache::get($idempotencyKey);
        }

        // Process the request and cache the response
        $response = $next($request);
        if ($idempotencyKey) {
            Cache::put($idempotencyKey, $response, now()->addMinutes(60));
        }

        return $response;
    }
}
