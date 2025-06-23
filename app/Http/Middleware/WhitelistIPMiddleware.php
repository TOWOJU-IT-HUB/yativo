<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\WhitelistedIP;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

class WhitelistIPMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Allow public routes without IP filtering
        if (
            $request->is('callback/*') ||
            $request->is('webhook/*') ||
            $request->is('redirect/*') ||
            $request->is('volet/payin/success')
        ) {
            Log::info('Request allowed without IP check: ' . $request->url());
            return $next($request);
        }

        // Get client IP considering proxy headers
        $remoteAddress = $request->header('X-FORWARDED-FOR')
            ? trim(explode(',', $request->header('X-FORWARDED-FOR'))[0])
            : $request->server('REMOTE_ADDR');

        // Strip port if present (e.g., 1.2.3.4:5678)
        if (strpos($remoteAddress, ':') !== false) {
            $remoteAddress = explode(':', $remoteAddress)[0];
        }

        if (!$remoteAddress) {
            Log::channel('whitelist_ip')->warning('Missing IP address in request headers');
            return get_error_response(['error' => 'Missing IP address'], 400);
        }

        if (!filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            Log::channel('whitelist_ip')->warning("Invalid IP format: $remoteAddress");
            return get_error_response(['error' => "Invalid IP address $remoteAddress"], 400);
        }

        // ✅ Static IP ranges allowed at firewall and app level
        $allowedStatic = [
            '192.168.1.50',
            '192.17.1.0/24',
            '10.17.0.0/16',
            '50.7.115.5',
            '51.255.40.139',
            '20.8.24.149',
            '192.168.55.241',
        ];

        if (IpUtils::checkIp($remoteAddress, $allowedStatic)) {
            Log::info("Request allowed from static whitelist IP: $remoteAddress");
            return $next($request);
        }

        // ✅ Check dynamic whitelist in DB
        $isWhitelisted = WhitelistedIP::whereJsonContains('ip_address', $remoteAddress)->exists();

        if ($isWhitelisted) {
            Log::info("Request allowed from DB whitelist IP: $remoteAddress");
            return $next($request);
        }

        // ✅ Check subdomains of yativo.com from referer or origin
        $referer = $request->headers->get('referer');
        $origin = $request->headers->get('origin');

        $isAllowedSubdomain = function ($url) {
            $host = parse_url($url, PHP_URL_HOST);
            return $host && preg_match('/(^|\.)yativo\.com$/i', $host);
        };

        if (($referer && $isAllowedSubdomain($referer)) || ($origin && $isAllowedSubdomain($origin))) {
            Log::info("Request allowed from yativo.com subdomain | Referer: $referer | Origin: $origin");
            return $next($request);
        }

        // ❌ Deny everything else
        Log::channel('whitelist_ip')->warning("Blocked IP: $remoteAddress | URL: " . $request->fullUrl());
        return get_error_response(['error' => 'Unauthorized Request, IP not whitelisted'], 403);
    }
}
