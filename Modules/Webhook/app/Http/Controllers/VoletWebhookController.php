<?php

namespace Modules\Webhook\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use Illuminate\Http\Request;

class VoletWebhookController extends Controller
{
    /**
     * Handle AdvCash (Volet) IPN notifications
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handleIpn(Request $request)
    {
        // Validate the request
        $this->validateIpnRequest($request);

        // Process based on transaction type
        switch ($request->input('ac_transaction_type')) {
            case 'INCOMING_WIRE_TRANSFER':
                return $this->handlePayin($request);
            case 'OUTGOING_WIRE_TRANSFER':
                return $this->handlePayout($request);
            default:
                return response()->json(['message' => 'Unsupported transaction type'], 400);
        }
    }

    /**
     * Validate the IPN request
     *
     * @param Request $request
     * @return void
     */
    private function validateIpnRequest(Request $request)
    {
        $request->validate([
            'ac_transfer' => 'required|string',
            'ac_start_date' => 'required|date',
            'ac_src_wallet' => 'required|string',
            'ac_dest_wallet' => 'required|string',
            'ac_amount' => 'required|numeric',
            'ac_merchant_currency' => 'required|string',
            'ac_transaction_type' => 'required|string',
            'ac_hash' => 'required|string',
        ]);

        // Verify hash signature
        $hash = $request->input('ac_hash');
        $calculatedHash = $this->calculateHash($request);

        if ($hash !== $calculatedHash) {
            abort(403, 'Invalid signature');
        }
    }

    /**
     * Calculate hash for verification
     *
     * @param Request $request
     * @return string
     */
    private function calculateHash(Request $request)
    {
        $secretKey = config('services.advcash.secret_key');

        $stringToHash = implode(':', [
            $request->input('ac_transfer'),
            $request->input('ac_start_date'),
            $request->input('ac_src_wallet'),
            $request->input('ac_dest_wallet'),
            $request->input('ac_amount'),
            $request->input('ac_merchant_currency'),
            $secretKey
        ]);

        return hash('sha256', $stringToHash);
    }

    /**
     * Handle payin transaction
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    private function handlePayin(Request $request)
    {
        try {
            // Log the transaction
            \Log::info('AdvCash Payin IPN', $request->all());

            // Update transaction status in database
            $transaction = TransactionRecord::where('transaction_id', $request->input('ac_transfer'))->first();

            if ($transaction) {
                $transaction->update([
                    'status' => 'completed',
                    'amount' => $request->input('ac_amount'),
                    'currency' => $request->input('ac_merchant_currency'),
                    'processed_at' => now(),
                ]);
            }

            return response()->json(['message' => 'Payin processed successfully']);
        } catch (\Exception $e) {
            \Log::error('AdvCash Payin IPN Error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Handle payout transaction
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    private function handlePayout(Request $request)
    {
        try {
            // Log the transaction
            \Log::info('AdvCash Payout IPN', $request->all());

            // Update withdrawal status in database
            $withdrawal = Withdraw::where('reference', $request->input('ac_transfer'))
                ->first();

            if ($withdrawal) {
                $withdrawal->update([
                    'status' => 'completed',
                    'processed_amount' => $request->input('ac_amount'),
                    'processed_currency' => $request->input('ac_merchant_currency'),
                    'processed_at' => now(),
                ]);
            }

            return response()->json(['message' => 'Payout processed successfully']);
        } catch (\Exception $e) {
            \Log::error('AdvCash Payout IPN Error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

}
