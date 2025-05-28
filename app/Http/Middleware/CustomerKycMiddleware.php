<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        // Skip validation if the request is for the KYC verification URL
        if ($request->is('api/v1/verification/verify-customer') OR $request->is('api/v1/customer/kyc/*')) {
            return $next($request);
        }

        // Proceed with validation if 'customer_id' is present in the request
        if ($request->has('customer_id') && auth()->check()) {
            $customerId = $request->customer_id;

            $customer = Customer::where('customer_id', $customerId)->first();

            if (!$customer) {
                return get_error_response(['error' => 'Invalid or unapproved customer'], 403);
            }
    
            // Check if customer is suspended
            // if ($customer->customer_status !== 'active') {
            //     return get_error_response(['message' => 'Customer is suspended'], 403);
            // }
    
            // Validate KYC status or bridge ID
            if (in_array($customer->customer_kyc_status, ['active', 'approved', 'completed']) || !empty($customer->bridge_customer_id)) {
                return $next($request);
            }
        }
        
        return $next($request);
        // return get_error_response(['message' => 'Customer not allowed'], 403);
    }
}
