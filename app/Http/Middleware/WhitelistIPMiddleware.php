<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\WhitelistedIP;

class WhitelistIPMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $clientIp = $request->ip();
        $allowedIps = ['50.7.115.5', '51.255.40.139'];

        if (in_array($clientIp, $allowedIps)) {
            $requestContent = file_get_contents('php://input');
            \Log::info('Incoming request from ' . $clientIp . ': ' . $requestContent);
        }

        $allowedDomains = [
            parse_url(getenv('APP_URL'), PHP_URL_HOST),
            'api.yativo.com',
            'zeenah.azurewebsites.net', '50.7.115.5', '51.255.40.139'
        ];

        // Check if the request is coming from allowed domains
        $referrer = $request->headers->get('referer');
        $referrerHost = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;

        if ($referrerHost && in_array($referrerHost, $allowedDomains)) {
            return $next($request);
        }

        // Retrieve the user
        $user = $request->user();
        if ($user) {
            $ip = $request->ip();

            // Check if the user's IP is whitelisted
            $whitelistedIPs = WhitelistedIP::where('user_id', $user->id)->pluck('ip_address')->toArray();

            if (in_array($ip, $whitelistedIPs)) {
                return $next($request);
            }
        }

        // Return error response for unauthorized access
        return get_error_response(['error' => 'Unauthorized Request, IP not whitelisted'], 403);
    }
}
