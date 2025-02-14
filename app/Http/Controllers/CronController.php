<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\PayinMethods;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Log;
use Modules\BinancePay\app\Http\Controllers\BinancePayController;
use Modules\BinancePay\app\Models\BinancePay;
use Modules\Flow\app\Http\Controllers\FlowController;

class CronController extends Controller
{
    public function index(Request $request)
    {
        //update status for deposits
        $this->getTransFiStatus();
        $this->getBinancePayStatus();
        $this->getFloidStatus();
        $this->getBridgeStatus();
        // $this->getBinancePayStatus();
        // $this->getBinancePayStatus();
        // $this->getBinancePayStatus();

        // run general failed update
        // $this->payout();
        // $this->deposit();
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

    // get status of Floid transaction
    private function getFloidStatus(): void
    {
        $ids = $this->getGatewayPayinMethods(method: 'floid');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $floid = new FlowController();
    
        foreach ($deposits as $deposit) {
            $order = null;
    
            // Fetch payment status based on currency
            switch (strtolower($deposit->currency)) {
                case 'clp':
                    $order = $floid->getChlPaymentStatus($deposit->gateway_deposit_id);
                    break;
                case 'pen':
                    $order = $floid->getPenPaymentStatus($deposit->gateway_deposit_id);
                    break;
                default:
                    continue; // Skip if currency is unsupported
            }
    
            // Ensure we received a valid response
            if (!isset($order['status'])) {
                continue; // Skip processing if no status
            }
    
            $txn = TransactionRecord::where('transaction_id', $deposit->id)->first();
            if (!$txn) {
                continue; // Skip if no related transaction record
            }
    
            $transactionStatus = strtolower($order['status']);
    
            if ($transactionStatus === "success") {
                $depositService = new DepositService();
                $depositService->process_deposit($txn->transaction_id);
            } else {
                $txn->update([
                    "transaction_status" => $transactionStatus
                ]);
    
                $deposit->update([
                    'status' => $transactionStatus
                ]);
            }
    
            // $this->updateTracking($deposit->id, $order['status'], $order);
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
}
