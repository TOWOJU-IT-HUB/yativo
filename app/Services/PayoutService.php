<?php

namespace App\Services;

use App\Http\Controllers\BridgeController;
use App\Http\Controllers\TransFiController;
use App\Models\Bridge;
use App\Models\Country;
use App\Models\Gateways;
use App\Models\payoutMethods;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\Withdraw;
use File;
use Illuminate\Support\Facades\Log;
use Modules\Advcash\app\Http\Controllers\AdvcashController;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\BinancePay\app\Http\Controllers\BinancePayController;
use Modules\Bitso\app\Http\Controllers\BitsoController;
use Modules\CoinPayments\app\Http\Controllers\CoinPaymentsController;
use Modules\Flutterwave\app\Http\Controllers\FlutterwaveController;
use Modules\LocalPayments\app\Http\Controllers\LocalPaymentsController;
use Modules\Monnet\app\Services\MonnetServices;
use Modules\Monnify\app\Http\Controllers\MonnifyController;
use Modules\PayPal\app\Http\Controllers\PayoutController;
use Modules\PayPal\app\Http\Controllers\PayPalDepositController;
use Modules\PayPal\app\Providers\PayPalServiceProvider;
use Modules\SendMoney\app\Models\SendQuote;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletController;
use Modules\VitaWallet\app\Services\VitaWalletService;
use Towoju5\Localpayments\Localpayments;
use App\Services\BrlaDigitalService;
use Modules\Flow\app\Http\Controllers\FlowController;


/**
 * This class is responsible for generating 
 * deposit/checkout link for customers to make payment.
 */
class PayoutService
{
    const ACTIVE = true;

    /**
     * check if gateway is active, 
     * then make request to gateway and 
     * return payment url or charge status for wallet
     */
    public function makePayment($quoteId, $c_gateway, $txn_type = 'withdrawal')
    {
        try {
            if ($txn_type == 'withdrawal') {
                $withdrawal = Withdraw::whereId($quoteId)->with('beneficiary')->first();
            }

            if (!$withdrawal) {
                return ['error' => "Unable to process withdrawal request, please contact support"];
            }

            // var_dump($withdrawal); exit;
            $gateway = $c_gateway->gateway;
            if ($gateway) {
                $result = self::$gateway($quoteId, $withdrawal->currency, $withdrawal);

                if(isset($result['error'])) {
                    return ['error' => $result['error']];
                }
                // var_dump($result); exit;

                TransactionRecord::create([
                    "user_id" => $withdrawal->user_id,
                    "transaction_beneficiary_id" => $withdrawal->user_id,
                    "transaction_id" => $quoteId,
                    "transaction_amount" => $withdrawal->amount,
                    "gateway_id" => $gateway,
                    "transaction_status" => "In Progress",
                    "transaction_type" => $txn_type ?? 'payout',
                    "transaction_memo" => "payout",
                    "transaction_currency" => $withdrawal->currency ?? "N/A",
                    "base_currency" => $withdrawal->currency ?? "N/A",
                    "secondary_currency" => $c_gateway->currency ?? "N/A",
                    "transaction_purpose" => request()->transaction_purpose ?? "Withdrawal",
                    "transaction_payin_details" => null,
                    "transaction_payout_details" => ['payout_data' => $withdrawal, "gateway_response" => $result],
                ]);

                Track::create([
                    "quote_id" => $quoteId,
                    "tracking_status" => "Send money inititated",
                    "transaction_type" => $txn_type ?? 'payout',
                ]);

                $withdrawal->update([
                    "status" => "processing"
                ]);

                return $result;
                // return back()->with('success', "Transaction initiated successfully");
            }

            return ['error' => 'Unable to process transaction at the moment'];
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function advcash($quoteId, $currency, $payoutObject)
    {
        try {
            $advcash = new AdvcashController();
            $init = $advcash->withdrawal($quoteId, $currency, $payoutObject);
            return $init;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function paypal($quoteId, $currency, $payoutObject): object|array
    {
        $paypal = new PayoutController();
        $beneficiaryId = request()->payment_method_id;
        $checkout = $paypal->init($quoteId, $currency, $payoutObject);
        return $checkout;
    }

    public function bitso($quoteId, $currency, $payoutObject)
    {
        try {
            $request = request();
            $beneficiaryId = $request->payment_method_id;
            $local = new BitsoController();
            Log::info("Bitso payout model called");
            $payout = $local->withdraw(
                $payoutObject->amount,
                $payoutObject->beneficiary_id,
                $currency,
                $payoutObject->id
            );
            return $payout;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function transfi($deposit_id, $currency, $payoutObject)
    {
        $transFi = new TransFiController();
        $checkout = $transFi->payout($deposit_id, $payoutObject->amount, $currency, $payoutObject);
        return $checkout;
    }

    public function bridge($deposit_id)
    {
        $bridge = new BridgeController();
        $checkout = $bridge->makePayout($deposit_id);
        return $checkout;
    }

    public function floid($quoteId, $currency, $payoutObject)
    {
        // echo "I am here";
        $flow = new FlowController();
        $checkout = $flow->payout($payoutObject, $payoutObject->amount, $currency);
        return $checkout;
        // return ['error' => 'Payout method is currently unavailable'];
    }
    
    public function vitawallet($quoteId, $currency, $payoutObject)
    {
        $request = request();
        try {
            $beneficiaryId = $payoutObject->beneficiary_id;
            $model = new BeneficiaryPaymentMethod();
            $beneficiary = $model->getBeneficiaryPaymentMethod($beneficiaryId);

            if (!$beneficiary) {
                return ['error' => 'Beneficiary not found'];
            }
            $gateway = payoutMethods::whereId($beneficiary->gateway_id)->first();
            if (!$gateway) {
                return ['error' => 'Gateway not found'];
            }
            
            $country = Country::where('currency_code', $gateway->currency)->where('iso3', $gateway->country)->first();
            
            // var_dump($country); exit;
            if (!$country) {
                return ['error' => 'Currency not supported'];
            }

            $rate = 1;
           
            Log::info("VitaWallet", ['currency1' => $gateway->currency, "currency2" => $payoutObject->currency]);
            
            $rate = getExchangeVal($gateway->currency, "CLP"); //$payoutObject->currency
            $formArray = (array) $beneficiary->payment_data;
            $requestBody = [
                "wallet" => env("VITAWALLET_WALLET_ID", "76f1d08e-9981-4d69-bfc5-edc0c1bc0574"),
                "transactions_type" => "withdrawal",
                "url_notify" => "https://api.yativo.com/callback/webhook/vitawallet", //route("vitawallet.callback.success"),
                "country" => $country->iso2,
                "currency" => "CLP",
                "amount" => $payoutObject->amount * $rate,
                "order" => $quoteId,
                "type" => "business_transaction",
                "beneficiary_email" => "emma@yativo.com"
            ];


            if (!isset($formArray['phone'])) {
                $requestBody['phone'] = auth()->user()?->phone ?? "9203751431";
            }

            if (!isset($formArray['phone'])) {
                $requestBody["city"] = $country->name;
            }

            if (!isset($formArray['purpose_comentary'])) {
                $requestBody["purpose_comentary"] = "For your school fee payment";
            }

            $payload = array_merge($formArray, $requestBody);

            // echo json_encode($payload, JSON_PRETTY_PRINT); exit;
            Log::info("My request payload is:", ['payload' => $payload]);
            $vita = new VitaWalletController();
            $prices = $vita->prices();

            Log::info("Price response is: ", ['price' => $prices]);

            $process = $vita->create_withdrawal($payload);

            Log::info("Main process response is: ", ['main_vita' => $process]);
            if (!is_array($process)) {
                $process = json_decode($process, true);
            }


            if (isset($process['error'])) {
                return ['error' => $process['error']['message'] ?? $process['error'] ?? 'Unknown error occurred'];
            }

            if (isset($process["transaction"]["attributes"]["included"]["withdrawal"])) {
                return $process["transaction"]["attributes"]["included"]["withdrawal"];
            }

            return $process;
        } catch (\Throwable $th) {
            Log::error('VitaWallet payout error: ' . $th->getTrace());
            return ['error' => $th->getMessage()];
        }
    }

    public function brla($quoteId, $currency, $payoutObject)
    {
        $request = request();
        try {
            $amount = $payoutObject->amount;
            $beneficiaryId = $request->payment_method_id;
            $model = new BeneficiaryPaymentMethod();
            $beneficiary = $model->getBeneficiaryPaymentMethod($beneficiaryId);

            if (!$beneficiary) {
                return ['error' => 'Beneficiary not found'];
            }
            $gateway = payoutMethods::whereId($beneficiary->gateway_id)->first();
            if (!$gateway) {
                return ['error' => 'Gateway not found'];
            }

            $country = Country::where('currency_code', $gateway->currency)->where('iso3', $gateway->country)->first();
            if (!$country) {
                return ['error' => 'Currency not supported'];
            }

            $formArray = (array) $beneficiary->payment_data;

            $payload = array_filter([
                'pixKey' => $formArray['pixKey'] ?? null,
                'taxId' => $formArray['taxId'] ?? null,
                'amount' => $amount,
                'externalId' => $quoteId,
                'name' => $formArray['name'] ?? null,
                'ispb' => $formArray['ispb'] ?? null,
                'branchCode' => $formArray['branchCode'] ?? null,
                'accountNumber' => $formArray['accountNumber'] ?? null,
            ]);
            $brla = new BrlaDigitalService();
            $process = $brla->createPayOutOrder($payload);
            return $process;
        } catch (\Throwable $th) {
            Log::error('VitaWallet payout error: ' . $th->getMessage());
            return ['error' => $th->getMessage()];
        }
    }

    public function completePayout($transactionId, $status)
    {
        // Prepare the condition to find the TransactionRecord
        $where = [
            'transaction_memo' => 'payout',
            'transaction_id' => $transactionId,
        ];

        // Retrieve the transaction record
        $transactionRecord = TransactionRecord::where($where)->first();

        if (!$transactionRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction record not found.',
            ], 404);
        }

        // Update the transaction record status
        $transactionRecord->transacction_status = $status;
        $transactionRecord->save();

        // Check if the Withdraw model needs updating
        $withdraw = Withdraw::whereId($transactionId)->first();

        if ($withdraw) {
            $withdraw->status = $status;
            $withdraw->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction status updated successfully.',
        ]);
    }
}