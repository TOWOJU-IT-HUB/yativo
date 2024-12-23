<?php

namespace App\Services;

use App\Http\Controllers\CoinbaseOnrampController;
use App\Http\Controllers\OnrampController;
use App\Http\Controllers\TransFiController;
use App\Models\Deposit;
use App\Models\Gateways;
use App\Models\PayinMethods;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\User;
use Modules\Customer\app\Models\Customer;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletController;
use Spatie\WebhookServer\WebhookCall;
use Illuminate\Support\Facades\Log;
use Modules\Advcash\app\Http\Controllers\AdvcashController;
use Modules\BinancePay\app\Http\Controllers\BinancePayController;
use Modules\Bitso\app\Http\Controllers\BitsoController;
use Modules\CoinPayments\app\Http\Controllers\CoinPaymentsController;
use Modules\Flow\app\Http\Controllers\FlowController;
use Modules\Flow\app\Services\FlowServices;
use Modules\Flutterwave\app\Http\Controllers\FlutterwaveController;
use Modules\LocalPayments\app\Http\Controllers\LocalPaymentsController;
use Modules\LocalPayments\app\Services\LocalPaymentServices;
use Modules\Monnet\app\Http\Controllers\MonnetController;
use Modules\Monnet\app\Services\MonnetServices;
use Modules\Monnify\app\Http\Controllers\MonnifyController;
use Modules\PayPal\app\Http\Controllers\PayoutController;
use Modules\PayPal\app\Http\Controllers\PayPalDepositController;
use Modules\SendMoney\app\Http\Controllers\SendMoneyController;
use Modules\SendMoney\app\Jobs\CompleteSendMoneyJob;
use Modules\SendMoney\app\Models\SendMoney;
use Modules\SendMoney\app\Models\SendQuote;
use Modules\Webhook\app\Models\Webhook;
use Towoju5\Localpayments\Localpayments;

/**
 * This class is responsible for generating 
 * deposit/checkout link for customers to make payment.
 * 
 * @category Wallet_Top_Up
 * @package  Null
 * @author   Emmanuel A Towoju <towojuads@gmail.com>
 * @license  MIT www.yativo.com/license
 * @link     www.yativo.com
 */


class DepositService
{
    const ACTIVE = true;
    /**
     * check if gateway is active, 
     * then make request to gateway and 
     * return payment url or charge status for wallet
     */
    public function makeDeposit(string $gateway, $currency, $amount, $send, $txn_type = "deposit")
    {
        try {
            $paymentMethods = PayinMethods::whereId($gateway)->first();
            // session()->put('payin_object', $paymentMethods);
            if ($paymentMethods) {
                $model = strtolower($paymentMethods->gateway);
                $result = self::$model($send['id'], $amount, $currency, $txn_type, $paymentMethods);
            }

            switch ($txn_type) {
                case "deposit":
                    $txn_amount = $send['amount'];
                    break;
                default:
                    $quote = SendQuote::find($send['quote_id']);
                    if (!$quote) {
                        return ['error' => 'Invalid quote.'];
                    }
                    $txn_amount = $quote['send_amount'];
                    break;
            }

            TransactionRecord::create([
                "user_id" => auth()->id(),
                "transaction_beneficiary_id" => active_user(),
                "transaction_id" => $send['id'],
                "transaction_amount" => $txn_amount,
                "gateway_id" => $paymentMethods->id,
                "transaction_status" => "In Progress",
                "transaction_type" => $txn_type,
                "transaction_memo" => "payin",
                "transaction_currency" => $currency,
                "base_currency" => $currency,
                "secondary_currency" => $paymentMethods->currency,
                "transaction_purpose" => request()->transaction_purpose ?? "Deposit",
                "transaction_payin_details" => array_merge([$send, $result]),
                "transaction_payout_details" => [],
            ]);

            Track::create([
                "quote_id" => $send['id'],
                "transaction_type" => $txn_type ?? 'deposit',
                "tracking_status" => "Deposit initiated successfully",
                "raw_data" => (array) $result
            ]);
            Log::info($result);
            return $result;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function local_payment($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        try {
            $request = request();
            $paymentMethod = null;

            // Determine account file path based on environment
            $accountFilePath = public_path('pay-methods/' . (strtolower(getenv('LOCALPAYMENT_MODE')) === 'test' ? 'localpayment-account-test.json' : 'localpayment-account.json'));

            // Load and decode account data
            if (!file_exists($accountFilePath)) {
                throw new \Exception("File not found at path: " . $accountFilePath);
            }
            $accountData = json_decode(file_get_contents($accountFilePath), true);

            // Determine payment mode and method
            $payment_mode = strtolower($gateway->payment_mode) === 'apm' ? 'BankTransfer' : $gateway->payment_mode;
            if ($payment_mode === 'BankTransfer' && preg_match('/\((.*?)\)/', $gateway->method_name, $matches)) {
                $paymentMethod = strtolower($matches[1]);
            }

            // Find matching account details
            $payObj = collect($accountData)->first(function ($acc) use ($payment_mode, $gateway, $currency, $paymentMethod, $amount) {
                return strtolower($acc['country_iso3']) === strtolower($gateway->country)
                    && strtolower($acc['currency_iso3']) === strtolower($currency)
                    && strtolower($acc['paymentMethodType']) === strtolower($payment_mode)
                    && ($paymentMethod === null || strtolower($acc['name']) === strtolower($paymentMethod))
                    && (!isset($acc['minAmount']) || $amount >= $acc['minAmount'])
                    && (!isset($acc['maxAmount']) || $amount <= $acc['maxAmount']);
            });

            if (!$payObj) {
                return ['error' => 'Payment method is currently unavailable, please contact support'];
            }

            $customer = auth()->user();
            $accountNumber = $payObj['number'] ?? null;

            if (is_array($accountNumber) && isset($accountNumber['error'])) {
                return $accountNumber;
            }

            // Prepare payload based on payment mode
            $basePayload = [
                "paymentMethod" => [
                    "type" => ucfirst($payObj['paymentMethodType']),
                    "code" => $payObj['gateway_code'],
                    "flow" => strtolower($gateway->payment_mode) === 'apm' ? 'REDIRECT' : 'DIRECT',
                ],
                "externalId" => $deposit_id,
                "country" => strtoupper($payObj['country_iso3']),
                "amount" => floatval($amount),
                "currency" => strtoupper($gateway->currency),
                "accountNumber" => $accountNumber,
                "conceptCode" => "0003",
                "comment" => "Yativo payin transaction id: " . $deposit_id,
                "merchant" => [
                    "type" => "COMPANY",
                    "name" => "Zee Technologies SPA",
                    "email" => "michael@yativo.com",
                ],
            ];

            $payerDetails = [
                "type" => "INDIVIDUAL",
                "name" => $customer->firstName,
                "lastname" => $customer->lastName,
                "document" => [
                    "id" => $request->document_id ?? $customer->idNumber,
                    "type" => $request->document_type ?? $customer->idType,
                ],
                "email" => $customer->email,
            ];

            if (strtolower($gateway->payment_mode) === 'cash') {
                $payerDetails['bank'] = [
                    "name" => $request->bank_name,
                    "code" => $request->bank_code,
                    "account" => [
                        "number" => $request->account_number,
                        "type" => $request->account_type,
                    ],
                ];
            }

            $payload = array_merge($basePayload, ["payer" => $payerDetails]);

            // Initiate local payment
            $local = new Localpayments();
            $payin = $local->payin()->init($payload);

            // Handle responses
            if (!is_array($payin)) {
                return ['result' => $payin];
            }

            if (isset($payin['error']) && !is_array($payin['error'])) {
                $errors = json_decode($payin['error'], true);
                return ['error' => $errors['errors'] ?? $payin['error']];
            }

            if (isset($payin["qr"])) {
                return ['qr' => array_merge($payin["qr"], $payin['payment'])];
            }

            if (isset($payin['redirectUrl'])) {
                return $payin['redirectUrl'];
            }

            if (isset($payin['wireInstructions'], $payin['payment'])) {
                return array_merge($payin['payment'], $payin['wireInstructions']);
            }

            if (isset($payin['ticket'], $payin['payment'])) {
                return array_merge($payin['payment'], $payin['ticket']);
            }

            return (array) $payin;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function binance_pay($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        try {
            $binance = new BinancePayController();
            $init = $binance->init($deposit_id, $amount, $currency, $gateway, $txn_type);
            return $init;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage(), "trace" => $th->getTrace()];
        }
    }

    public function advcash($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        try {
            $advcash = new AdvcashController();
            $init = $advcash->initiatePayin($deposit_id, $amount, $currency, $txn_type, $gateway);
            return $init;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function flutterwave($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        try {
            $flutterwave = new FlutterwaveController();
            $init = $flutterwave->makePayment($deposit_id, $amount, $currency);
            return $init;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function coinpayment($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $coinpayment = new CoinPaymentsController();
        // echo json_encode([$deposit_id, $amount, $currency, $txn_type, $gateway]); exit;
        $checkout = $coinpayment->makePayment($deposit_id, $amount, $currency);
        if ($checkout['error'] == 'ok') {
            update_deposit_gateway_id($deposit_id, $checkout['result']['txn_id']);
            return $checkout['result']['checkout_url'];
        }
        return ["result" => $checkout['error']];
    }

    public function monnet($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $monnet = new MonnetServices();
        $checkout = $monnet->payin($deposit_id, $amount, $currency, 'DEPOSIT');
        return $checkout;
    }

    public function floid($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $flow = new FlowController();
        $checkout = $flow->makePayment($deposit_id, $amount, $currency);
        return $checkout;
    }

    public function transfi($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $transFi = new TransFiController();
        $checkout = $transFi->payin($deposit_id, $amount, $currency);
        return $checkout;
    }

    public function onramp($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $transFi = new OnrampController();
        $checkout = $transFi->payin(request());
        return $checkout;
    }

    public function transak($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $user = auth()->user();
        if (!empty($amount) && !empty($currency)) {
            $baseUrl = getenv('TRANSAK_BASE_URL');
            $queryParams = [
                'walletAddress' => "0x316363Fd9B3e7E9e1ea4cC8503681a15A0cc5ECb",
                'disableWalletAddressForm' => true,
                'network' => "ethereum",
                'cryptoCurrencyCode' => "USDT",
                'apiKey' => getenv('TRANSAK_API_KEY'),
                'fiatCurrency' => $currency,
                'fiatAmount' => $amount,
                'hideExchangeScreen' => true,
                'userData' => [
                    "firstName" => $user->firstName,
                    "lastName" => $user->lastName,
                    "email" => $user->email,
                    "mobileNumber" => $user->phoneNumber,
                    "dob" => $user->phone,
                    "address" => [
                        "addressLine1" => $user->street,
                        "addressLine2" => "San Francisco",
                        "city" => $user->city,
                        "state" => $user->state,
                        "postCode" => $user->zipCode,
                        "countryCode" => $user->country
                    ]
                ]
            ];

            $queryString = http_build_query($queryParams);

            $url = $baseUrl . '?' . $queryString;
            return $url;
        }
    }

    /**
     * Retrive user clabe info
     */
    private function bitso($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        // return [];
        $bitso = new BitsoController();
        $checkout = $bitso->deposit($amount, $currency);
        return $checkout;
    }

    /**
     * Retrive user clabe info
     */
    private function coinbase($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        // return [];
        $bitso = new CoinbaseOnrampController();
        request()->merge([
            'amount' => request()->amount
        ]);
        $checkout = $bitso->generateOnrampUrl($amount, $currency);
        return $checkout;
    }

    public function brla_qr($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $request = request();
        $checkout_id = rand(102930, 9999999);
        session()->put("checkout_id", $checkout_id);
        $payload['amount'] = $amount;
        $payload['referenceLabel'] = $checkout_id;
        if ($request->has('customer_id')) {
            $customer = Customer::where('customer_id', $request->customer_id)->first();
            if (!$customer->brla_subaccount_id) {
                // create and retrieve the brla_subaccount_id
            }
            //retrieve the customer sub_account_id
            $payload['subaccountId'] = $customer->brla_subaccount_id;
        }

        $brla = new BrlaDigitalService();
        $checkout = $brla->generatePayInBRCode($payload);
        return $checkout;
    }

    public function vitawallet($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $vitawallet = new VitaWalletController();
        $checkout = $vitawallet->payin($deposit_id, $amount, $currency);
        return $checkout;
    }

    /**
     * @param mixed $tranxRecord  of deposit
     * @return void DepositService.php
     */
    public function process_deposit($tranxRecord)
    {
        try {
            Log::channel('deposit_error')->info("initiating deposit for: $tranxRecord");
            $order = TransactionRecord::whereId($tranxRecord)->first();
            if (!$order) {
                $where = [
                    "transaction_memo" => "payin",
                    "transaction_id" => $tranxRecord
                ];
                $order = TransactionRecord::where($where)->first();
            } else {
                Log::channel('deposit_error')->info("Error processing transactions", [$order, $tranxRecord]);
                die();
            }

            $quoteId = $order['transaction_id'];

            switch ($order['transaction_status']) {
                case 'success':
                    Log::channel('deposit_error')->info('Transaction already processed', [$order]);
                    http_response_code(200);
                    break;
                case 'pending':
                case 'In Progress':
                    Log::channel('deposit_error')->info('processing deposit', $order->toArray());
                    $payin = payinMethods::where('id', $order['gateway_id'])->first();
                    $user = User::findOrFail($order['user_id']);
                    $order->update(['transaction_status' => SendMoneyController::SUCCESS]);

                    $deposit = Deposit::whereId($order['transaction_id'])->where('status', 'pending')->first();
                    if ($deposit) {
                        $deposit->status = SendMoneyController::SUCCESS;
                        if ($deposit->save()) {
                            $this->complete_deposit($deposit, $user, $order, $payin);
                        }
                    }
                default:
                    Log::channel('deposit_error')->info('Transaction status is ' . $order['transaction_status']);
                    break;
            }
            http_response_code(200);
        } catch (\Throwable $th) {
            Log::channel('deposit_error')->error("Error on deposit ID: {$tranxRecord} " . $th->getMessage(), ['error' => $th->getMessage()]);
        }
    }

    /**
     * @param object $deposit
     *  
     * @return void
     */
    private function complete_deposit(Deposit $deposit, User $user, TransactionRecord $order, PayinMethods $payin)
    {
        Log::info("deposit crediting for {$user->id}, Params: ", $deposit->toArray());
        $wallet = $user->getWallet($deposit->deposit_currency);
        $credit_amount = $deposit->amount;

        $get_rate = exchange_rates(strtoupper($payin->currency), strtoupper($deposit->deposit_currency));
        $credit_amount = ($get_rate * $deposit->amount) * 100;

        // top up the customer 

        if ($wallet->deposit($credit_amount)) {
            Log::info("Deposit crediting completed for {$user->id}, Params: ", $order->toArray());

            $webhook_url = Webhook::whereUserId($user->id)->first();

            if ($webhook_url) {
                WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                    "event.type" => "deposit_success",
                    "payload" => array_merge($order->toArray(), [
                        "deposit_amount" => $deposit->amount * 100,
                        "credited_amount" => $credit_amount,
                        "user_id" => $user->id,
                        "wallet_type" => "credit",
                        "transaction_type" => "deposit",
                        "transaction_id" => $order['transaction_id'],
                        "transaction_status" => "success",
                        "transaction_reference" => $order['transaction_reference']
                    ])
                ])->dispatchSync();
            }
        }

        Track::create([
            "quote_id" => $order['transaction_id'],
            "tracking_status" => "Deposit completed successfully",
            "raw_data" => $order
        ]);
    }
}
