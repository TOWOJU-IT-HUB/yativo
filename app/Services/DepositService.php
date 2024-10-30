<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\Gateways;
use App\Models\PayinMethods;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\User;
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

            if ($txn_type == "deposit") {
                $txn_amount = $send['amount'];
            } else {
                $quote = SendQuote::find($send['quote_id']);
                if (!$quote) {
                    return ['error' => 'Invalid quote.'];
                }
                $txn_amount = $quote['send_amount'];
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
                "transaction_purpose" => request()->transaction_purpose ?? "Deposit",
                "transaction_payin_details" => array_merge([$send, $result]),
                "transaction_payout_details" => [],
            ]);

            Track::create([
                "quote_id" => $send['id'],
                "tracking_status" => "Deposit initiated successfully",
                "raw_data" => (array) $result
            ]);

            return $result;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function local_payment($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        try {
            // ddd($amount);
            $request = request();
            $paymentMethod = null;
            if (strtolower(getenv('LOCALPAYMENT_MODE')) === 'test') {
                $accs = json_decode(file_get_contents(public_path("pay-methods/localpayment-account-test.json")), true);
            } else {
                $accs = json_decode(file_get_contents(public_path("pay-methods/localpayment-account.json")), true);
            }

            if (strtolower($gateway->payment_mode) === 'apm') {
                $payment_mode = "BankTransfer";
                if (preg_match('/\((.*?)\)/', $gateway->method_name, $matches)) {
                    $paymentMethod = strtolower($matches[1]);
                }
            } else {
                $payment_mode = $gateway->payment_mode;
            }


            foreach ($accs as $acc) {
                $isMatch = strtolower($acc['country_iso3']) === strtolower($gateway->country)
                    && strtolower($acc['currency_iso3']) === strtolower($currency)
                    // && strtolower($acc["transactionType"]) === "payin"
                    && strtolower($acc['paymentMethodType']) === strtolower($payment_mode);

                if ($paymentMethod !== null) {
                    $isMatch = $isMatch && strtolower($acc['name']) === strtolower($paymentMethod);
                }

                if ($isMatch) {
                    if (isset($acc['minAmount']) && $amount < $acc['minAmount']) {
                        return ['error' => 'Amount is below the minimum allowed: ' . $acc['minAmount']];
                    }

                    if (isset($acc['minAmount']) && $amount > $acc['maxAmount']) {
                        return ['error' => 'Amount exceeds the maximum allowed: ' . $acc['maxAmount']];
                    }

                    $payObj = (object) $acc;
                    break;
                }
            }

            if (!isset($payObj)) {
                return ['error' => 'Payment method is currently unavailable, please contact support'];
            }


            $customer = auth()->user();
            $name = explode(" ", $customer->name);

            $accountNumber = $payObj->number;

            if (is_array($accountNumber) && isset($accountNumber['error'])) {
                return $accountNumber;
            }

            switch (strtolower($gateway->payment_mode)) {
                case 'cash':
                    $payload = [
                        "paymentMethod" => [
                            "type" => "Cash",
                            "code" => $payObj->gateway_code,
                            "flow" => "REDIRECT"
                        ],
                        "externalId" => $deposit_id,
                        "country" => $gateway->country,
                        "amount" => floatval($amount),
                        "currency" => $gateway->currency,
                        "accountNumber" => $accountNumber,
                        "conceptCode" => "0003",
                        "comment" => "Yativo payin transaction id: " . $deposit_id,
                        "merchant" => [
                            "type" => "COMPANY",
                            "name" => "Zee Technologies SPA",
                            "email" => "michael@yativo.com"
                        ],
                        "payer" => [
                            "type" => "INDIVIDUAL",
                            "name" => $customer->firstName,
                            "lastname" => $customer->lastName,
                            "document" => [
                                "id" => $customer->idNumber,
                                "type" => $customer->idType
                            ],
                            "email" => $customer->email,
                            "bank" => [
                                "name" => $request->bank_name,
                                "code" => $request->bank_code,
                                "account" => [
                                    "number" => $request->account_number,
                                    "type" => $request->account_type
                                ],
                            ],
                        ]
                    ];
                    // if ($customer->is_business) {
                    //     unset($payload['payer']);
                    //     $payload['payer'] = [
                    //         "type" => "COMPANY",
                    //         "name" => $customer->firstName,
                    //         "document" => [
                    //             "id" => $customer->idNumber,
                    //             "type" => $customer->idType
                    //         ],
                    //         "email" => $customer->email,
                    //         "bank" => [
                    //             "name" => $request->bank_name,
                    //             "code" => $request->bank_code,
                    //             "account" => [
                    //                 "number" => $request->account_number,
                    //                 "type" => $request->account_type
                    //             ],
                    //         ],
                    //     ];
                    // }
                    break;
                case 'banktransfer':
                    $payload = [
                        "paymentMethod" => [
                            "type" => ucfirst($payObj->paymentMethodType),
                            "code" => $payObj->gateway_code,
                            "flow" => "DIRECT"
                        ],
                        "externalId" => $deposit_id,
                        "country" => strtoupper($payObj->country_iso3),
                        "amount" => floatval($amount),
                        "currency" => strtoupper($gateway->currency),
                        "accountNumber" => $accountNumber,
                        "conceptCode" => "0003",
                        "comment" => "Yativo payin transaction id: " . $deposit_id,
                        "merchant" => [
                            "type" => "COMPANY",
                            "name" => "Zee Technologies SPA",
                            "email" => "michael@yativo.com"
                        ],
                        "payer" => [
                            "type" => "INDIVIDUAL",
                            "name" => $customer->firstName,
                            "lastname" => $customer->lastName,
                            "document" => [
                                "id" => $request->document_id ?? $customer->idNumber,
                                "type" => $request->document_type ?? $customer->idType
                            ],
                            "email" => $customer->email,
                            "bank" => [
                                "name" => $request->bank_name,
                                "code" => $request->bank_code,
                                "account" => [
                                    "number" => $request->account_number,
                                    "type" => $request->account_type
                                ],
                            ],
                        ]
                    ];

                    // if ($customer->is_business) {
                    //     unset($payload['payer']);
                    //     $payload['payer'] = [
                    //         "type" => "COMPANY",
                    //         "name" => $customer->firstName,
                    //         "document" => [
                    //             "id" => $customer->idNumber,
                    //             "type" => $customer->idType
                    //         ],
                    //         "email" => $customer->email,
                    //         "bank" => [
                    //             "name" => $request->bank_name,
                    //             "code" => $request->bank_code,
                    //             "account" => [
                    //                 "number" => $request->account_number,
                    //                 "type" => $request->account_type
                    //             ],
                    //         ],
                    //     ];
                    // }
                    break;
                case 'apm':
                    $payload = [
                        "paymentMethod" => [
                            "type" => ucfirst($payObj->paymentMethodType),
                            "code" => $payObj->gateway_code,
                            "flow" => "REDIRECT"
                        ],
                        "externalId" => $deposit_id,
                        "country" => $gateway->country,
                        "amount" => floatval($amount),
                        "currency" => $gateway->currency,
                        "accountNumber" => $accountNumber,
                        "conceptCode" => "0003",
                        "comment" => "Yativo payin transaction id: " . $deposit_id,
                        "merchant" => [
                            "type" => "COMPANY",
                            "name" => "Zee Technologies SPA",
                            "email" => "michael@yativo.com"
                        ],
                        "payer" => [
                            "type" => "INDIVIDUAL",
                            "name" => $customer->firstName,
                            "lastname" => $customer->lastName,
                            "document" => [
                                "id" => $request->document_id ?? $customer->idNumber,
                                "type" => $request->document_type ?? $customer->idType
                            ],
                            "email" => $customer->email,
                        ]
                    ];
                    // if ($customer->is_business) {
                    //     unset($payload['payer']);
                    //     $payload['payer'] = [
                    //         "type" => "COMPANY",
                    //         "name" => $customer->firstName,
                    //         "document" => [
                    //             "id" => $customer->idNumber,
                    //             "type" => $customer->idType
                    //         ],
                    //         "email" => $customer->email
                    //     ];
                    // }
                    break;

                default:
                    # code...
                    break;
            }

            $local = new Localpayments();

            $payin = $local->payin()->init($payload);


            if (!is_array($payin)) {
                result($payin);
            }

            if (isset($payin['error']) && !is_array($payin['error'])) {
                $arr = json_decode($payin['error'], true);
                if (isset($arr['errors'])) {
                    return ['error' => $arr['errors']];
                }
            }

            if (isset($payin["qr"])) {
                return ['qr' => array_merge($payin["qr"], $payin['payment'])];
            }

            if (isset($payin['redirectUrl'])) {
                return $payin['redirectUrl'];
            }

            if (isset($payin['wireInstructions']) && isset($payin['payment'])) {
                return array_merge($payin['payment'], $payin['wireInstructions']);
            }


            if (isset($payin['ticket']) && isset($payin['payment'])) {
                return array_merge($payin['payment'], $payin['ticket']);
            }


            return (array) $payin;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage(),];
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

    public function flow($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $flow = new FlowController();
        $checkout = $flow->makePayment($deposit_id, $amount, $currency);
        return $checkout;
    }

    /**
     * Retrive user clabe info
     */
    private function bitso($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        // return [];
        $bitso = new BitsoController();
        $checkout = $bitso->deposit($amount);
        return $checkout;
    }

    public function brla_qr($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $checkout_id = rand(102930, 9999999);
        session()->put("checkout_id", $checkout_id);
        $payload['amount'] = $amount;
        $payload['referenceLabel'] = $checkout_id;

        $brla = new BrlaDigitalService();
        $checkout = $brla->generatePayInBRCode($payload);
        return $checkout;
    }

    public function vitawallet($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $vitawallet = new VitaWalletController();
        $checkout = $vitawallet->payin($amount, $currency);
        return $checkout;
    }

    /**
     * @param mixed $tranxRecord  of deposit
     * @return void
     */
    public function process_deposit($tranxRecord)
    {
        try {
            Log::channel('deposit_error')->info("initiating deposit for: $tranxRecord");
            $order = TransactionRecord::whereId($tranxRecord)->first();
            if (!$order) {
                Log::channel('deposit_error')->info("Error processing transactions", [$order, $tranxRecord]);
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

                    switch (strtolower($order->transaction_type)) {
                        case "deposit":
                            $deposit = Deposit::whereId($order['transaction_id'])->where('status', 'pending')->first();
                            if ($deposit) {
                                $deposit->status = SendMoneyController::SUCCESS;
                                if ($deposit->save()) {
                                    $this->complete_deposit($deposit, $user, $order, $payin);
                                }
                            }
                            break;
                        case "send_money":
                            $send_money = SendMoney::where('quote_id', $quoteId)->where('status', 'pending')->first();
                            if ($send_money) {
                                CompleteSendMoneyJob::dispatchAfterResponse($quoteId);
                            }
                            break;
                    }
                    break;
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
