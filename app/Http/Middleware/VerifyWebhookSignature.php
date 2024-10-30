<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->header('x-onramp-payload');
        $signature = $request->header('x-onramp-signature');
        $localSignature = hash_hmac('sha512', $payload, env('ONRAMP_API_KEY'));

        if ($localSignature === $signature) {
            // signature verified
            return $next($request);
        }

        return response('Invalid signature passed', 403);
    }
}
