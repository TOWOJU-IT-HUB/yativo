<?php

namespace App\Services;

use App\Http\Controllers\TransFiController;
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
    public function makePayment($quoteId, $gateway, $txn_type = 'withdrawal')
    {
        try {
            if ($txn_type == 'withdrawal') {
                $withdrawal = Withdraw::whereId($quoteId)->with('beneficiary')->first();
            }

            if (!$withdrawal) {
                return ['error' => "Unable to process withdrawal request, please contact support"];
            }

            // var_dump($withdrawal); exit;

            if ($gateway) {
                $result = self::$gateway($quoteId, $withdrawal->currency, $withdrawal);

                if(isset($result['error'])) {
                    return back()->with('error', $result['error']);
                }
                // var_dump($result); exit;

                TransactionRecord::create([
                    "user_id" => auth()->id(),
                    "transaction_beneficiary_id" => auth()->id(),
                    "transaction_id" => $quoteId,
                    "transaction_amount" => $withdrawal->amount,
                    "gateway_id" => $gateway,
                    "transaction_status" => "In Progress",
                    "transaction_type" => $txn_type ?? 'payout',
                    "transaction_memo" => "payout",
                    "transaction_currency" => $withdrawal->currency,
                    "base_currency" => $withdrawal->currency,
                    "secondary_currency" => $gateway->currency,
                    "transaction_purpose" => request()->transaction_purpose ?? "Withdrawal",
                    "transaction_payin_details" => $withdrawal->beneficiary->payment_object,
                    "transaction_payout_details" => $withdrawal,
                ]);

                Track::create([
                    "quote_id" => $quoteId,
                    "tracking_status" => "Send money inititated",
                    "transaction_type" => $txn_type ?? 'payout',
                ]);

                return $result;
            }

            return ['error' => 'Unable to process transaction at the moment'];
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage(), 'trace' => $th->getTraceAsString()];
        }
    }

    public function binance_pay($quoteId, $currency, $payoutObject)
    {
        try {
            $binance = new BinancePayController();
            $init = $binance->withdrawal($quoteId, $payoutObject->amount, $currency);
            return $init;
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

    public function flutterwave($quoteId, $currency, $payoutObject)
    {
        try {
            $flutterwave = new FlutterwaveController();
            $init = $flutterwave->payout($quoteId, $payoutObject->amount, $payoutObject->toArray());
            return $init;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function coinpayments($quoteId, $currency, $payoutObject): object|array
    {
        $coinpayment = new CoinPaymentsController();
        $checkout = $coinpayment->pay($quoteId, $currency, $payoutObject);
        if (isset($checkout['error']) and $checkout['error'] == 'ok') {
            return $checkout['result'];
        } else {
            return $checkout;
        }

    }

    public function paypal($quoteId, $currency, $payoutObject): object|array
    {
        $paypal = new PayoutController();
        $beneficiaryId = request()->payment_method_id;
        $checkout = $paypal->init($quoteId, $currency, $payoutObject);
        return $checkout;
    }

    public function monnet($quoteId, $currency, $payoutObject): object|array
    {
        // var_dump("Here i am. monnet serveices"); exit;
        $request = request();
        $monnet = new MonnetServices();
        $beneficiaryId = $request->payment_method_id;
        $checkout = $monnet->payout(
            $request->amount,
            $currency,
            $beneficiaryId,
            $quoteId
        );

        if (!isset($checkout['errors'])) {
            return $checkout;
        }
        return ['error' => $checkout["errors"]];
    }

    public function local_payment($quoteId, $currency, $payoutObject)
    {
        try {
            $request = request();
            $beneficiaryId = $request->payment_method_id;
            $local = new LocalPaymentsController();
            $payout = $local->payout(
                $request->amount,
                $currency,
                $beneficiaryId,
                $quoteId
            );
            return $payout;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function bitso($quoteId, $currency, $payoutObject)
    {
        try {
            $request = request();
            $beneficiaryId = $request->payment_method_id;
            $local = new BitsoController();
            $payout = $local->withdraw(
                $request->amount,
                $beneficiaryId,
                $currency
            );
            return $payout;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function transFi($deposit_id, $amount, $currency, $payoutObject)
    {
        $transFi = new TransFiController();
        $checkout = $transFi->payout($deposit_id, $amount, $currency, $payoutObject);
        return $checkout;
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
            // var_dump($gateway); exit;

            // $country = Country::where('currency_code', strtoupper($gateway->currency))->first();
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
            ];

            if (isset($formArray['email'])) {
                $requestBody['beneficiary_email'] = $formArray['email'];
            }

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

            $vitawallet = new VitaWalletController();
            $process = $vitawallet->create_withdrawal($payload);

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
            Log::error('VitaWallet payout error: ' . $th->getMessage());
            return ['error' => $th->getMessage()];
        }
    }

    public function transak($quoteId, $amount, $currency)
    {
        if (!empty($amount) && !empty($currency)) {
            $baseUrl = getenv('TRANSAK_BASE_URL');

            $queryParams = [
                'network' => "ethereum",
                'cryptoCurrencyCode' => "USDC",
                'apiKey' => getenv('TRANSAK_API_KEY'),
                'fiatCurrency' => $currency,
                'fiatAmount' => $amount
            ];

            $queryString = http_build_query($queryParams);

            $url = $baseUrl . '?' . $queryString;
            return $url;
        }
    }

    public function brla($quoteId, $amount, $currency, $payoutObject)
    {
        $request = request();
        try {
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

            $payload = [
                'pixKey' => $formArray['pixKey'] ?? null,
                'taxId' => $formArray['taxId'] ?? null,
                'amount' => $amount,
                'externalId' => $quoteId,
                'name' => $formArray['name'] ?? null,
                'ispb' => $formArray['ispb'] ?? null,
                'branchCode' => $formArray['branchCode'] ?? null,
                'accountNumber' => $formArray['accountNumber'] ?? null,
            ];
            $brla = new BrlaDigitalService();
            $process = $brla->createPayOutOrder($payload);
            return $process;
        } catch (\Throwable $th) {
            Log::error('VitaWallet payout error: ' . $th->getMessage());
            return ['error' => $th->getMessage()];
        }
    }
}

