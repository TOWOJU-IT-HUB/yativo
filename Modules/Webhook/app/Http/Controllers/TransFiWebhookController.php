<?php

namespace Modules\Webhook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business\TransFi;
use App\Models\Deposit;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Log;

class TransFiWebhookController extends Controller
{
    private $dedicatedSecret = '<DEDICATED_SECRET>'; // Replace with your actual secret

    public function handleWebhook(Request $request)
    {
        $signature = $request->header('X-Transfi-Hmac-Hash');
        $body = $request->getContent();

        // Verify the signature
        $computedHash = hash_hmac('sha256', $body, $this->dedicatedSecret);

        if ($computedHash !== $signature) {
            Log::warning('Webhook signature verification failed.');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        // Determine event type and status
        $status = $payload['status'] ?? null;
        Track::create([
            "quote_id" => $payload['order']['orderId'],
            "tracking_status" => $status,
            "transaction_type" => $txn_type ?? 'payout',
        ]);

        if ($status === 'deposit_completed' || $status === 'fund_settled') {
            $this->updateDepositStatus($payload['order']['orderId'], 'success');
        } elseif ($status === 'withdraw_completed') {
            $this->updateWithdrawalStatus($payload['order']['orderId'], 'completed');
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Update the status of a deposit in the database.
     */
    private function updateDepositStatus($orderId, $newStatus)
    {
        // Update Deposit model
        $deposit = Deposit::where('deposit_id', $orderId)->first();
        if ($deposit) {
            $deposit->status = $newStatus;
            $deposit->save();
            Log::info("Deposit updated: Order ID {$orderId}, Status {$newStatus}");
        }

        // Update TransactionRecord model
        $transaction = TransactionRecord::where('transaction_id', $orderId)->first();
        if ($transaction) {
            $transaction->status = $newStatus;
            $transaction->save();
            Log::info("TransactionRecord updated: Order ID {$orderId}, Status {$newStatus}");
        }
    }

    /**
     * Update the status of a withdrawal in the database.
     */
    private function updateWithdrawalStatus($orderId, $newStatus)
    {
        // Update Withdrawal model
        $withdrawal = Withdraw::whereId($orderId)->first();
        if ($withdrawal) {
            $withdrawal->status = $newStatus;
            $withdrawal->save();
            Log::info("Withdrawal updated: Order ID {$orderId}, Status {$newStatus}");
        }

        // Update TransactionRecord model
        $transaction = TransactionRecord::where('transaction_id', $orderId)->first();
        if ($transaction) {
            $transaction->status = $newStatus;
            $transaction->save();
            Log::info("TransactionRecord updated: Order ID {$orderId}, Status {$newStatus}");
        }
    }
}
