<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Withdraw;
use Illuminate\Http\Request;

class CronController extends Controller
{
    public function index(Request $request)
    {
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
    private function deposit()
    {

        $now = now();

        $failedDepositIds = Deposit::query()
            ->with('depositGateway:id,settlement_time') // Only load necessary fields
            ->where('status', 'pending')
            ->whereHas('depositGateway', function ($query) {
                $query->whereNotNull('settlement_time');
            })
            ->get()
            ->filter(function ($deposit) use ($now) {
                $settlementTimeHours = $deposit->depositGateway->settlement_time;
                $settlementThreshold = $deposit->created_at->addHours(floor($settlementTimeHours))
                    ->addMinutes(($settlementTimeHours - floor($settlementTimeHours)) * 60);

                return $now->greaterThan($settlementThreshold);
            })
            ->pluck('id'); // Collect IDs of failed deposits

        // Bulk update failed deposits
        if ($failedDepositIds->isNotEmpty()) {
            Deposit::whereIn('id', $failedDepositIds)->update(['status' => 'failed']);
        }
    }
}
