<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Log;
use Modules\BinancePay\app\Http\Controllers\BinancePayController;
use Modules\BinancePay\app\Models\BinancePay;
use Modules\Flow\app\Http\Controllers\FlowController;
use Modules\Bitso\app\Services\BitsoServices;

class CronController extends Controller
{
    public function index(Request $request)
    {
        //update status for deposits
        // $this->getTransFiStatus();
        // $this->getBinancePayStatus();
        // $this->getFloidStatus();
        // $this->getBridgeStatus();
        // $this->getBinancePayStatus();
        // $this->getBinancePayStatus();
        // $this->getBinancePayStatus();

        // run general failed update
        // $this->payout();
        // $this->deposit();

        $this->checkForBridgeVirtualAccountDeposits();
    }

    private function payout()
    {
        $now = now();

        // $failedPayoutIds = Withdraw::query()
        //     ->with('payoutGateway:id,estimated_delivery') // Only load necessary fields
        //     ->where('status', 'pending')
        //     ->whereHas('payoutGateway', function ($query) {
        //         $query->whereNotNull('estimated_delivery');
        //     })
        //     ->get()
        //     ->filter(function ($payout) use ($now) {
        //         $estimatedDeliveryHours = $payout->payoutGateway->estimated_delivery;
        //         $deliveryThreshold = $payout->created_at->addHours(floor($estimatedDeliveryHours))
        //             ->addMinutes(($estimatedDeliveryHours - floor($estimatedDeliveryHours)) * 60);

        //         return $now->greaterThan($deliveryThreshold);
        //     })
        //     ->pluck('id'); // Collect IDs of failed payouts

        // // Bulk update failed payouts
        // if ($failedPayoutIds->isNotEmpty()) {
        //     Withdraw::whereIn('id', $failedPayoutIds)->update(['status' => 'failed']);
        // }

        $failedPayoutIds = Withdraw::query()
            ->with('payoutGateway:id,estimated_delivery') // Only load necessary fields
            ->where('status', 'pending')
            ->whereHas('payoutGateway', function ($query) {
                $query->whereNotNull('estimated_delivery');
            })
            ->get()
            ->filter(function ($payout) use ($now) {
                $estimatedDeliveryHours = $payout->payoutGateway->estimated_delivery;
                $deliveryThreshold = $payout->created_at->addHours(floor($estimatedDeliveryHours))
                    ->addMinutes(($estimatedDeliveryHours - floor($estimatedDeliveryHours)) * 60);

                return $now->greaterThan($deliveryThreshold);
            })
            ->pluck('id');

        $cancelledPayoutIds = Withdraw::query()
            ->doesntHave('payoutGateway')
            ->where('status', 'pending')
            ->pluck('id');

        if ($failedPayoutIds->isNotEmpty()) {
            Withdraw::whereIn('id', $failedPayoutIds)->update(['status' => 'failed']);
        }

        if ($cancelledPayoutIds->isNotEmpty()) {
            Withdraw::whereIn('id', $cancelledPayoutIds)->update(['status' => 'cancelled']);
        }
    }

    private function deposit()
    {

        $now = now();

        // $failedDepositIds = Deposit::query()
        //     ->with('depositGateway:id,settlement_time')
        //     ->where('status', 'pending')
        //     ->whereHas('depositGateway', function ($query) {
        //         $query->whereNotNull('settlement_time');
        //     })
        //     ->get()
        //     ->filter(function ($deposit) use ($now) {
        //         $settlementTimeHours = $deposit->depositGateway->settlement_time;
        //         $settlementThreshold = $deposit->created_at->addHours(floor($settlementTimeHours))
        //             ->addMinutes(($settlementTimeHours - floor($settlementTimeHours)) * 60);

        //         return $now->greaterThan($settlementThreshold);
        //     })
        //     ->pluck('id'); // Collect IDs of failed deposits

        // // Bulk update failed deposits
        // if ($failedDepositIds->isNotEmpty()) {
        //     Deposit::whereIn('id', $failedDepositIds)->update(['status' => 'failed']);
        // }
    }

    // bitso withdrawal payout 
    public function bitso()
    {
        $bitso = new BitsoServices();
        $ids = $this->getGatewayPayoutMethods(method: 'bitso');
        $payouts = Withdraw::whereIn('gateway_id', $ids)->whereStatus('pending')->get();
        
        foreach ($payouts as $payout) {
            $txn_id = $payout->id;
            $curl = $bitso->getPayoutStatus($txn_id);
            if(isset($curl['success']) && $curl['success'] != false){
                Log::info("Below is the payout detail: ", ["curl" => $curl]);
                $payload = $curl['payload'][0];
                $payout->status = strtolower($payload['status']);
                $payout->save();

                // update transaction record also
                $txn = TransactionRecord::where(['transaction_id' => $txn_id, 'transaction_memo' => 'payout'])->first();
                if($txn) {
                    $txn->transaction_status = $payout->status;
                    $txn->save();
                }

                if($payout->save() && $txn->save()) {
                    // if transaction is failed refund customer
                    if(strtolower($payout->status) === "failed") {
                        $user = User::whereId($payout->user_id)->first();
                        if($user) {
                            $wallet = $user->getWallet($payout->debit_wallet);
                            $wallet->deposit($payout->debit_amount, [
                                "description" => "refund",
                                "full_desc" => "Refund for payout {$payout->id}",
                                "payload" => $payout
                            ]);
                        }
                    }
                }
            }
            Log::info("Curl response to retrieve data from Bitso: ", ['curl' => $curl]);
        }
    }

    // get status of transFi transaction
    private function getTransFiStatus(): void
    {
        $ids = $this->getGatewayPayinMethods(method: 'transfi');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $transFi = new TransFiController();
        foreach ($deposits as $deposit) {
            $order = $transFi->getOrderDetails($deposit->gateway_deposit_id);
            if (isset($order['status']) && $order['status'] == "success") {
                $payload = $order['data'];
                /** Check if order is Deposit - Payin */
                if ($payload['type'] == "pay" && $payload['status'] == "fund_settled") {
                    $txn = TransactionRecord::where('transaction_id', $payload['order_id'])->first();

                    $deposit = Deposit::where('gateway_deposit_id', $payload['order_id'])->first();
                    if ($txn) {
                        $where = [
                            "transaction_memo" => "payin",
                            "transaction_id" => $payload['order_id']
                        ];
                        $order = TransactionRecord::where($where)->first();
                        if ($order) {
                            $deposit_services = new DepositService();
                            $deposit_services->process_deposit($order->transaction_id);
                        }
                    }
                    // return redirect()->to(env('WEB_URL'));
                }
            }
        }
    }


    // get status of BinancePay transaction
    private function getBinancePayStatus(): void
    {
        $ids = $this->getGatewayPayinMethods(method: 'binance_pay');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();

        foreach ($deposits as $deposit) {
            $order = TransactionRecord::where("transaction_id", $deposit->deposit_id)->first();
            $verify = BinancePayController::verifyOrder($deposit->gateway_deposit_id);

            if (isset($verify['data']) && $verify['data']['status'] === "PAID") {
                $where = [
                    "transaction_memo" => "payin",
                    "transaction_id" => $deposit->deposit_id
                ];
                $order = TransactionRecord::where($where)->first();
                if ($order) {
                    $deposit_services = new DepositService();
                    $deposit_services->process_deposit($order->transaction_id);
                    $this->updateTracking($deposit->id, $verify['data']['status'], $verify);
                }
            }
        }
    }

    // get status of Bridge transaction
    private function getBridgeStatus(): void
    {
        $ids = $this->getGatewayPayinMethods(method: 'bridge');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();

        foreach ($deposits as $deposit) {
            $order = TransactionRecord::where("transaction_id", $deposit->deposit_id)->first();
            $verify = BinancePayController::verifyOrder($deposit->gateway_deposit_id);

            if (isset($verify['data']) && $verify['data']['status'] === "PAID") {
                $where = [
                    "transaction_memo" => "payin",
                    "transaction_id" => $deposit->deposit_id
                ];
                $order = TransactionRecord::where($where)->first();
                if ($order) {
                    $deposit_services = new DepositService();
                    $deposit_services->process_deposit($order->transaction_id);
                } else {
                    // update the status withoutout completing the deposit
                    $order->update([
                        "transaction_status" => strtolower($order['status'])
                    ]);
                    $deposit->update([
                        'status' => strtolower($order['status'])
                    ]);
                }
                $this->updateTracking($deposit->id, $verify['data']['status'], $verify);
            }
        }
    }
    
    private function getGatewayPayinMethods(string $method)
    {
        return PayinMethods::where('gateway', $method)
            ->pluck('id')
            ->toArray();
    }

    private function getGatewayPayoutMethods(string $method)
    {
        return payoutMethods::where('gateway', $method)
            ->pluck('id')
            ->toArray();
    }

    private function updateTracking($quoteId, $trakingStatus, $response)
    {
        Track::create([
            "quote_id" => $quoteId,
            "transaction_type" => "deposit",
            "tracking_status" => $trakingStatus,
            "raw_data" => (array) $response,
            "tracking_updated_by" => "cron"
        ]);
    }

    public function vitawallet()
    {
        $ids = $this->getGatewayPayinMethods('vitawallet');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $vitawallet = new VitaWalletController();

        foreach ($deposits as $deposit) {
            $curl = $vitawallet->getPayout($deposit->gateway_deposit_id);
            if (is_array($curl) && isset($curl['transaction'])) {
                $record = $curl['transaction'];
                if (isset($record['status'])) {
                    $transactionStatus = strtolower($record['status']);
                    
                    $now = now();
                    $failedPayoutIds = Withdraw::query()
                        ->with('payoutGateway:id,estimated_delivery') // Only load necessary fields
                        ->where('status', 'pending')
                        ->whereHas('payoutGateway', function ($query) {
                            $query->whereNotNull('estimated_delivery');
                        })
                        ->get()
                        ->filter(function ($payout) use ($now) {
                            $estimatedDeliveryHours = $payout->payoutGateway->estimated_delivery;
                            $deliveryThreshold = $payout->created_at->addHours(floor($estimatedDeliveryHours))
                                ->addMinutes(($estimatedDeliveryHours - floor($estimatedDeliveryHours)) * 60);

                            return $now->greaterThan($deliveryThreshold);
                        })
                        ->pluck('id');

                    $cancelledPayoutIds = Withdraw::query()
                        ->doesntHave('payoutGateway')
                        ->where('status', 'pending')
                        ->pluck('id');

                    if ($failedPayoutIds->isNotEmpty()) {
                        Withdraw::whereIn('id', $failedPayoutIds)->update(['status' => 'failed']);
                    }

                    if ($cancelledPayoutIds->isNotEmpty()) {
                        Withdraw::whereIn('id', $cancelledPayoutIds)->update(['status' => 'cancelled']);
                    }
                }
            }
        }
    }

    public function checkForBridgeVirtualAccountDeposits()
    {
        $lastEventId = Cache::get('bridge_last_event_id');
        $queryParams = $lastEventId ? ['starting_after' => $lastEventId] : [];
        $queryParams['event_type'] = 'payment_processed';

        $response = Http::withToken(env('BRIDGE_API_KEY'))
            ->get(env('BRIDGE_BASE_URL') . "/v0/virtual_accounts/history", $queryParams);

        if ($response->successful() && isset($response['data']) && !empty($response['data'])) {
            foreach ($response['data'] as $eventData) {
                $this->processVirtualAccountWebhook($eventData);
                // Update the last event ID
                if (!empty($eventData['id'])) {
                    Cache::put('bridge_last_event_id', $eventData['id']);
                }
            }
        } else {
            Log::warning('Bridge webhook fetch returned empty or failed', ['response' => $response->json()]);
        }

    }

    protected function processVirtualAccountWebhook(array $eventData): void
    {
        $accountId = $eventData['virtual_account_id'] ?? null;
        $customer = Customer::where('bridge_customer_id', $eventData['customer_id'] ?? null)->first();

        if (!$accountId || !$customer) {
            Log::error("Missing virtual account ID or customer not found", ['eventData' => $eventData]);
            return;
        }

        $vc = VirtualAccount::where("account_id", $accountId)->first();

        if (!$vc) {
            Log::error("Virtual account not found for ID: $accountId");
            return;
        }

        $payload = $eventData;

        $user = User::find($vc->user_id);
        if (!$user) {
            Log::error("User not found for virtual account ID: $accountId");
            return;
        }

        $vc_status = strtolower($payload['type']) === "payment_processed"
            ? \App\Http\Controllers\Api\V1\SendMoneyController::SUCCESS
            : "pending";

        // Ensure necessary deposit columns exist
        if (Schema::hasTable('deposits') && Schema::hasColumn('deposits', 'deposit_currency')) {
            Schema::table('deposits', function ($table) {
                $table->string('deposit_currency')->nullable()->change();
                $table->string('currency')->nullable()->change();
                $table->string('receive_amount')->nullable()->change();
            });
        }

        $sent_amount = $payload['receipt']['initial_amount'] ?? $payload['amount'] ?? 0;
        $payment_rail = $payload['source']['payment_rail'] ?? null;
        $percentage = floatval(0.60 / 100);
        $float_fee = floatval($sent_amount * $percentage);

        if ($payment_rail === "ach_push") {
            $fixed_fee = 0.60;
        } else {
            $fixed_fee = 25.00;
        }

        $total_fee = $float_fee + $fixed_fee;
        $deposit_amount = floatval($sent_amount - $total_fee);

        // Create or update Deposit
        $deposit = Deposit::updateOrCreate(
            ['gateway_deposit_id' => $payload["id"]],
            [
                'user_id' => $user->id,
                'amount' => $deposit_amount,
                'currency' => 'usd',
                'deposit_currency' => 'USD',
                'gateway' => 99999999,
                'status' => $vc_status,
                'receive_amount' => $deposit_amount,
                'meta' => $payload,
            ]
        );

        // Create or update VirtualAccountDeposit
        VirtualAccountDeposit::updateOrCreate(
            [
                'user_id' => $deposit->user_id,
                'deposit_id' => $deposit->id,
            ],
            [
                'currency' => strtoupper($payload['currency'] ?? 'USD'),
                'amount' => $deposit_amount,
                'account_number' => $vc->account_number,
                'status' => $vc_status,
            ]
        );

        // Create TransactionRecord
        TransactionRecord::create([
            'user_id' => $user->id,
            'transaction_beneficiary_id' => $user->id,
            'transaction_id' => $payload['deposit_id'] ?? Str::uuid(),
            'transaction_amount' => $deposit_amount,
            'gateway_id' => 99999999,
            'transaction_status' => $vc_status,
            'transaction_type' => 'virtual_account',
            'transaction_memo' => 'payin',
            'transaction_currency' => strtoupper($payload['currency'] ?? 'USD'),
            'base_currency' => strtoupper($payload['currency'] ?? 'USD'),
            'secondary_currency' => strtoupper($payload['currency'] ?? 'USD'),
            'transaction_purpose' => 'VIRTUAL_ACCOUNT_DEPOSIT',
            'transaction_payin_details' => [
                'sender_name' => $payload['source']['sender_name'] ?? null,
                'trace_number' => $payload['source']['trace_number'] ?? null,
                'bank_routing_number' => $payload['source']['sender_bank_routing_number'] ?? null,
                'description' => $payload['source']['description'] ?? null,
                'transaction_fees' => $total_fee
            ],
            'transaction_payout_details' => null,
        ]);

        // Credit wallet if not already done
        if ($vc_status === \App\Http\Controllers\Api\V1\SendMoneyController::SUCCESS) {
            $wallet = $user->getWallet('usd');
            if ($wallet) {
                $existingTransaction = $wallet->transactions()
                    ->where('meta->deposit_id', $deposit->id)
                    ->first();

                if (!$existingTransaction) {
                    $wallet->deposit($deposit_amount * 100, [
                        'deposit_id' => $deposit->id,
                        'gateway_deposit_id' => $payload['id'],
                        'sender' => $payload['source']['description'] ?? null
                    ]);
                }
            }
        }

        // Send webhook
        $webhookData = [
            "event.type" => "virtual_account.deposit",
            "payload" => [
                "amount" => $payload['amount'],
                "currency" => "USD",
                "status" => "completed",
                "credited_amount" => $deposit_amount,
                "transaction_type" => "virtual_account_topup",
                "transaction_id" => "TXN" . rand(100000, 999999),
                "customer" => $customer,
                "source" => $payload['source'] ?? [],
            ]
        ];

        dispatch(function () use ($user, $webhookData) {
            $webhook = Webhook::whereUserId($user->id)->first();
            if ($webhook) {
                WebhookCall::create()
                    ->meta(['_uid' => $webhook->user_id])
                    ->url($webhook->url)
                    ->useSecret($webhook->secret)
                    ->payload($webhookData)
                    ->dispatchSync();
            }
        })->afterResponse();
    }
}
