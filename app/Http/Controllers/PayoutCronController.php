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

class PayoutCronController extends Controller
{
    public function index(Request $request)
    {
        //update status for deposits
        $this->getTransFiStatus();
        $this->getBinancePayStatus();

        // run general failed update
        $this->payout();
        $this->deposit();
    }

    private function payout()
    {
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
            ->pluck('id'); // Collect IDs of failed payouts

        // Bulk update failed payouts
        if ($failedPayoutIds->isNotEmpty()) {
            Withdraw::whereIn('id', $failedPayoutIds)->update(['status' => 'failed']);
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
            "transaction_type" => "payout", 
            "tracking_status" => $trakingStatus,
            "raw_data" => (array) $response,
            "tracking_updated_by" => "cron"
        ]);
    }
}
