<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\WhitelistedIP;
use Symfony\Component\HttpFoundation\IpUtils;

class WhitelistIPMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Get client IP considering proxies
        $remoteAddress = $request->header('X-FORWARDED-FOR') 
            ? explode(',', $request->header('X-FORWARDED-FOR'))[0] 
            : $request->server('REMOTE_ADDR');

        // Remove port if included (e.g., 102.91.4.193:4054 -> 102.91.4.193)
        if (strpos($remoteAddress, ':') !== false) {
            $remoteAddress = explode(':', $remoteAddress)[0];
        }

        // Validate if the IP is missing
        if (!$remoteAddress) {
            \Log::channel('whitelist_ip')->warning('Missing IP address in request headers.');
            return get_error_response(['error' => 'Missing IP address'], 400);
        }

        // Validate if the IP is invalid
        if (!filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            \Log::channel('whitelist_ip')->warning("Invalid IP address $remoteAddress");
            return get_error_response(['error' => "Invalid IP address $remoteAddress"], 400);
        }

        // Define allowed IPs and ranges
        $allowedAddresses = [
            '192.168.1.50',
            '192.17.1.50/24',
            '10.17.0.0/16',
            '50.7.115.5',
            '51.255.40.139',
            '20.8.24.149',
            '192.168.55.241',
        ];

        // Check if the IP matches any static whitelist
        if (IpUtils::checkIp($remoteAddress, $allowedAddresses)) {
            \Log::info('Request allowed from static whitelist IP: ' . $remoteAddress);
            return $next($request);
        }

        // Check if the IP is in the WhitelistedIP database
        $isWhitelisted = WhitelistedIP::where('ip_address', $remoteAddress)->exists();
        if ($isWhitelisted) {
            \Log::info('Request allowed from database whitelist IP: ' . $remoteAddress);
            return $next($request);
        }

        // Check the referer or origin header for subdomains of yativo.com
        $referer = $request->headers->get('referer');
        $origin = $request->headers->get('origin');

        // Helper function to validate subdomains
        $isAllowedSubdomain = function ($url) {
            $host = parse_url($url, PHP_URL_HOST);
            return $host && preg_match('/\.yativo\.com$/', $host);
        };

        if (($referer && $isAllowedSubdomain($referer)) || ($origin && $isAllowedSubdomain($origin))) {
            \Log::info('Request allowed from yativo.com subdomain: Referer=' . $referer . ', Origin=' . $origin);
            return $next($request);
        }

        // Log unauthorized access attempt
        \Log::channel('whitelist_ip')->warning('Blocked IP: ' . $remoteAddress . ' | Referer: ' . $referer . ' | Origin: ' . $origin);

        // Return unauthorized response
        return get_error_response(['error' => 'Unauthorized Request, IP not whitelisted'], 403);
    }
}
