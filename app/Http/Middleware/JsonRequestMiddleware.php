<?php

namespace App\Http\Middleware;

use App\Models\BusinessConfig;
use Closure;
use Http;

class JsonRequestMiddleware
{
    public function handle($request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();

            $businessConfig = $user->businessConfig;

            if (!$businessConfig) {
                // If not, create a new one
                $businessConfig = BusinessConfig::create([
                    'user_id' => $request->user()->id,
                    'configs' => [
                        "can_issue_visa_card" => false,
                        "can_issue_master_card" => false,
                        "can_issue_bra_virtual_account" => false,
                        "can_issue_mxn_virtual_account" => false,
                        "can_issue_arg_virtual_account" => false,
                        "can_issue_usdt_wallet" => false,
                        "can_issue_usdc_wallet" => false,
                        "charge_business_for_deposit_fees" => false,
                        "charge_business_for_payout_fees" => false,
                        "can_hold_balance" => false,
                        "can_use_wallet_module" => false,
                        "can_use_checkout_api" => false
                    ]
                ]);
            }

            if ($user->roles->isEmpty()) {
                $user->assignRole($user->account_type);
            }

        }

        // Http::get(url('generate-ref-accounts'));
        
        $request->headers->add(['Accept' => 'application/json']);
        return $next($request);
    }
}

