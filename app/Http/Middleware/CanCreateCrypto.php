<?php

namespace App\Http\Middleware;

use App\Models\BusinessConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanCreateCrypto
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // if(auth()->check()){
        //     $businessConfig = BusinessConfig::where('user_id', auth()->id())->pluck('configs');
        //     $currency = $request->currency;
        //     $canIssueWalletKey = 'can_issue_' . strtolower(explode('.', $currency)[0]) . '_wallet';
        //     if (!$businessConfig->$canIssueWalletKey) {
        //         return get_error_response(['error' => 'Business not approved for service']);
        //     }
        // }
        return $next($request);
    }
}
