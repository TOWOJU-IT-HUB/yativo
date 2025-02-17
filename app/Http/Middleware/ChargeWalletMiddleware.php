<?php

namespace App\Http\Middleware;

use App\Models\BeneficiaryFoems;
use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Models\payoutMethods;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;

class ChargeWalletMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if ($request->has('amount')) {

                $user = $request->user();

                $amount = $request->amount;
                $fees = 0;
                $finalAmount = $amount + $fees;
                if ($request->has('payment_method_id')) {
                    // transaction is withdrawal to beneficiary
                    $beneficiary = BeneficiaryPaymentMethod::whereId($request->payment_method_id)->first();
                    if (!$beneficiary) {
                        return get_error_response(['error' => 'Beneficiary not found']);
                    }
                    
                    $payoutMethod = payoutMethods::whereId($beneficiary->gateway_id)->first();
                    if(!$payoutMethod) {
                        return get_error_response(['error' => "Selected payout method was not found"]);
                    }
                    $allowedCurrencies = [];
                    $allowedCurrencies = explode(',', $payoutMethod->base_currency);
                    
                    if (!in_array($request->debit_wallet, $allowedCurrencies)) {
                        return get_error_response([
                            'error' =>  "The selected wallet is not supported for selected gateway. Allowed currencies: " . $payoutMethod->base_currency
                        ], 400);
                    }


                    //    response()->json($beneficiary->toArray());
                    $chargeNow = debit_user_wallet($finalAmount, $beneficiary->currency);
                } 
                
                // else {
                //     // for endpoints like Yativo to Yativo transfer
                //     $chargeNow = debit_user_wallet($finalAmount, $request->currency);
                // }

                if (isset($chargeNow['error'])) {
                    return get_error_response(['error' => $chargeNow['error']]);
                }

                if ($chargeNow && !isset($chargeNow['error'])) {
                    return $next($request);
                } else {
                    return get_error_response(['error' => 'Insufficient wallet balance']);
                }
            }

            return get_error_response(['error' => "Sorry, we're currently unable to process your transaction"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }

        return $next($request);
    }
}
