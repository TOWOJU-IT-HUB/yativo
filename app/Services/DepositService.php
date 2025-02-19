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
 * @author   Emmanuel A Towoju <emma@yativo.com>
 * @license  YAT - www.yativo.com/license
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

    public function floid($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $flow = new FlowController();
        $checkout = $flow->makePayment($deposit_id, $amount, $currency);
        return $checkout;
    }

    public function transfi($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $transFi = new TransFiController();
        $checkout = $transFi->payin($deposit_id, $amount, $currency, $txn_type, $gateway);
        return $checkout;
    }

    public function onramp($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $transFi = new OnrampController();
        $checkout = $transFi->payin(request());
        return $checkout;
    }

    /**
     * Retrive user clabe info
     */
    private function bitso($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        // return [];
        $bitso = new BitsoController();
        $checkout = $bitso->deposit($deposit_id, $amount, $currency);
        return $checkout;
    }

    public function brla($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $request = request();
        $checkout_id = rand(102930, 9999999);
        session()->put("checkout_id", $checkout_id);
        $payload['amount'] = round($amount, 2);
        $payload['referenceLabel'] = $checkout_id;
        if ($request->has('customer_id')) {
            $customer = Customer::where('customer_id', $request->customer_id)->first();
            if (!$customer->brla_subaccount_id) {
                // create and retrieve the brla_subaccount_id
            }
            //retrieve the customer sub_account_id
            $payload['subaccountId'] = $customer->brla_subaccount_id;
            Log::info('Brla qr pix data', ['brla_subaccount_id' => $customer->brla_subaccount_id, 'referenceLabel' => $checkout_id]);
        }
        update_deposit_gateway_id($deposit_id, $checkout_id);
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

    private function khipu()
    {
        return ['error' => 'Deposit method is currently unavailable'];
    }

    /**
     * @param mixed $tranxRecord  of deposit
     * @return void DepositService.php
     */
    public function process_deposit($tranxRecord)
    {
        try {
            Log::channel('deposit_error')->info("initiating deposit for: $tranxRecord");
            $order = TransactionRecord::whereId($tranxRecord)->orWhere('transaction_id', $tranxRecord)->first();
            if (!$order) {
                $where = [
                    "transaction_memo" => "payin",
                    "transaction_id" => $tranxRecord
                ];
                $order = TransactionRecord::where($where)->first();
            } 

            Log::channel('deposit_error')->info("Log my incoming transaction data status: ", ['order' => $order]);
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
        }

        Track::create([
            "quote_id" => $order['transaction_id'],
            "tracking_status" => "Deposit completed successfully",
            "raw_data" => $order
        ]);
    }
}
