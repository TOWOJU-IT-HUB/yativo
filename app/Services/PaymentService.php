<?php

namespace App\Services;

use App\Models\Gateways;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Modules\Advcash\app\Http\Controllers\AdvcashController;
use Modules\BinancePay\app\Http\Controllers\BinancePayController;
use Modules\CoinPayments\app\Http\Controllers\CoinPaymentsController;
use Modules\Flow\app\Http\Controllers\FlowController;
use Modules\Flow\app\Services\FlowServices;
use Modules\Flutterwave\app\Http\Controllers\FlutterwaveController;
use Modules\Monnet\app\Http\Controllers\MonnetController;
use Modules\Monnet\app\Services\MonnetServices;
use Modules\Monnify\app\Http\Controllers\MonnifyController;
use Modules\PayPal\app\Http\Controllers\PayPalDepositController;
use Modules\PayPal\app\Providers\PayPalServiceProvider;
use Modules\Pomelo\app\Http\Controllers\PomeloController;
use Modules\SendMoney\app\Jobs\SaveSendMoneyResponse;
use Modules\SendMoney\app\Models\SendMoney;
use Modules\SendMoney\app\Models\SendQuote;


/**
 * This class is responsible for generating 
 * deposit/checkout link for customers to make payment.
 */
class PaymentService
{
    const ACTIVE = true;
    /**
     * check if gateway is active, 
     * then make request to gateway and 
     * return payment url or charge status for wallet
     */
    public function makePayment(SendMoney $send, $gateway)
    {
        try {
            $quote = SendQuote::find($send->quote_id);
            $amount = $quote->send_amount;
            $currency = $quote->send_currency;
            if ($gateway == 'wallet') {
                $user = User::find(active_user());
                if ($user->hasWallet($currency)) {
                    $walletBalance = $user->getWallet($currency);
                    if ($walletBalance < $amount) {
                        if (getenv('APP_DEBUG') == true) {
                            Log::info("Insuficient balance for $user->id transaction amount  $amount on " . now());
                            echo json_encode(['error' => "Please contact support to continue"]); exit;
                        }
                        return false;
                    }
                    // return true;
                    echo json_encode(['error' => "Please contact support to continue"]); exit;
                }
            }

            $paymentMethods = Gateways::whereStatus(self::ACTIVE)->get();
            foreach ($paymentMethods as $methods) {
                if ($gateway == $methods->slug && gateways($methods->slug) == true) {
                    $model = strtolower($methods->slug);
                    $result = self::$model($send->id, $amount, $currency);

                    TransactionRecord::create([
                        "user_id" => auth()->id(),
                        "transaction_beneficiary_id" => $quote->beneficiary()->id,
                        "transaction_id" => $quote->id,
                        "transaction_amount" => $amount,
                        "gateway_id" => $gateway->id,
                        "transaction_status" => "In Progress",
                        "transaction_type" => "payin-payout",
                        "transaction_memo" => "2-way sending",
                        "transaction_purpose" => request()->transaction_purpose ?? "Send Money",
                        "transaction_payin_details" => $quote->beneficiary()->payment_object,
                        "transaction_payout_details" => $quote,
                    ]);

                    Track::create([
                        "quote_id" => $quote->id,
                        "tracking_status" => "Send money inititated",
                        "transaction_type" => "send_money",
                    ]);

                }
            }

        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function local_payment($amount, $currency, $gateway)
    {
        //
    }

    public function binance_pay($quoteId, $amount, $currency)
    {
        try {
            $binance = new BinancePayController();
            $init = $binance->withdrawal($quoteId, $amount, $currency);
            return $init;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function advcash($quoteId, $amount, $currency)
    {
        // try {
        //     $advcash = new AdvcashController();
        //     $init = $advcash->init($quoteId, $amount, $currency);
        //     return $init;
        // } catch (\Throwable $th) {
        //     return ['error' => $th->getMessage()];
        // }
    }

    public function flutterwave($quoteId, $amount, $currency)
    {
        try {
            $flutterwave = new FlutterwaveController();
            $init = $flutterwave->makePayment($quoteId, $amount, $currency);
            return $init;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function monnify($quoteId, $amount, $currency)
    {
        try {
            $monnify = new MonnifyController();
            $init = $monnify->createCheckout($quoteId, $amount, $currency);
            return $init;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function coinpayment($quoteId, $amount, $currency): string
    {
        $checkout_url = "";
        $coinpayment = new CoinPaymentsController();
        $checkout = $coinpayment->makePayment($quoteId, $amount, $currency);
        SaveSendMoneyResponse::dispatch($quoteId, ["method" => "coinpayment", "quoteId" => $quoteId, "amount" => $amount, "currency" => $currency, "currency2" => "USDC"], $checkout);
        if (isset($checkout['result']['checkout_url'])) {
            $checkout_url = $checkout['result']['checkout_url'];
        }
        return $checkout_url['result'];
    }

    public function paypal($quoteId, $amount, $currency): string
    {
        $paypal = new PayPalDepositController();
        $checkout = $paypal->createOrder($quoteId, $amount, $currency);
        return $checkout;
    }

    public function monnet($quoteId, $amount, $currency)
    {
        // var_dump($quoteId, $amount, $currency); exit;
        $monnet = new MonnetServices();
        $checkout = $monnet->payin($quoteId, $amount, $currency);
        return $checkout;
    }

    public function flow($quoteId, $amount, $currency)
    {
        if (!in_array($currency, ["CLP"])) {
            return ["error" => "Unknown currency selected"];
        }
        $flow = new FlowController();
        $checkout = $flow->makePayment($quoteId, $amount, $currency);
        return $checkout;
    }

    public function pomelo($quoteId, $amount, $currency)
    {
        if (!in_array($currency, ["CLP"])) {
            return ["error" => "Unknown currency selected"];
        }
        $flow = new PomeloController();
        $checkout = $flow->makePayment($quoteId, $amount, $currency);
        return $checkout;
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
}

