<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerVirtualAccountMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if($request->has('customer_id')) {
            // check if user is approved to create virutal cards
            $customer = getCustomerById($request->customer_id);
            if($customer->can_create_va != true) {
                return get_error_response(['error' => 'customer is not yet approved for this service/endpoint']);
            }
        }
        return $next($request);
    }
}
