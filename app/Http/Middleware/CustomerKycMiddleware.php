<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Customer\app\Models\Customer;
use Symfony\Component\HttpFoundation\Response;

class CustomerKycMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(Route::is('customer.verification.customer')) {
            return $next($request);
        }
        if ($request->has('customer_id')) {
            $customerId = $request->customer_id;
            // Validate customer existence and KYC status
            $customer = Customer::where('customer_id', $customerId)
                ->where('customer_status', 'active')
                ->where('customer_kyc_status', 'approved')
                ->first();

            if (!$customer) {
                return response()->json(['error' => 'Invalid or unapproved customer'], 403);
            }
        }
        return $next($request);
    }
}
