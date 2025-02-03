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
        // Skip validation if the request is for the KYC verification URL
        if ($request->is('api/v1/verification/verify-customer')) {
            return $next($request);
        }

        // Proceed with validation if 'customer_id' is present in the request
        if ($request->has('customer_id')) {
            $customerId = $request->customer_id;

            // Validate customer existence and KYC status
            $customer = Customer::where('customer_id', $customerId)
                            ->where('customer_status', 'active')
                            ->where('customer_kyc_status', 'approved')
                            ->first();

            // If no valid customer is found, return a response with an error
            if (!$customer) {
                return get_error_response(['error' => 'Invalid or unapproved customer'], 403);
            }
        }

        // Continue with the request processing
        return $next($request);
    }
}
