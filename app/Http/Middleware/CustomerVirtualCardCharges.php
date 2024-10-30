<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerVirtualCardCharges
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('amount')) {
            $user = $request->user();
            $curr = $request->currency ?? "USD";
            $wallet = $user->getWallet($curr);
            if (!$wallet) {
                return get_error_response(['error' => "Please contact support, we can't complete your request at the momment"]);
            }

            // calculate the fees on the transaction
            try {
                $amount = $request->amount;
                $fees = 0;
                $finalAmount = $amount + $fees;
                if (debit_user_wallet($finalAmount)) {
                    return $next($request);
                }
            } catch (\Throwable $th) {
                return get_error_response(['error' => $th->getMessage()]);
            }
        }
        // return $next($request);
        return get_error_response(['error' => "Sorry, we're currently unable to process your transaction"]);
    }
}
