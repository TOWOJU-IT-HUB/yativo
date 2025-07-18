<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Customer\app\Models\Customer;

class BitHonorController extends Controller
{
    public $baseUrl;
    public function __construct()
    {
        $this->baseUrl = env("BITHONOR_BASE_URL", "https://api-test.spatransfer.com");
    }

    public function sendPaymentOrder($payoutObject, $amount, $currency = "VES", $payout = null)
    {
        $request = request();
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if (!$customer) {
            // use the user info since the request does not contain a customer
            $user = $request->user();
            $userName = $user->name;
            $userPhone = $user->phoneNumber;
        }

        $receiver = (object)$payoutObject;
        $payKey = "phoneNumber";
        if($receiver->payment_type == "TRF") {
            $payKey = "accountNumber";
        }
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-api-key' => env("BITHONOR_API_KEY"),
        ])->post("{$this->baseUrl}/spa/api/v1.2/send-payment-order", [
            "secret_company_key" => env("BITHONOR_SECRET_KEY"),
            "names" => $receiver->names,
            "docType" => $receiver->docType,
            "identNumber" => $receiver->docNumber,
            "payType" => $receiver->payment_type,
            "bankCode" => $receiver->bankCode,
            "currency" => $currency,
            "amount" => number_format($amount, 2),
            $payKey => $receiver->phoneNumber,
            "client_txid" => generate_uuid(),
        ]);

        // Return API response to the frontend
        if ($response->successful()) {
            $result = $response->json(); // $response
            if(isset($result['ticket_id']) && !is_null($payout)) {
                // update the payout with payment_gateway_id
                $payout->update([
                    "payment_gateway_id" => $result['ticket_id']
                ]);
            }
            return $result;
        } else {
            return ['error' => $response->body()];
        }
    }
    
    public function fetchPaymentOrder($ticketId)
    {
        try {
            $payload = [
                "secret_company_key" => "BCdgaWev",
                "filters" => [
                    "filter_field" => "ticket_id",
                    "search_field" => [$ticketId]
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => env("BITHONOR_API_KEY"),
            ])->post("{$this->baseUrl}/spa/api/v1.2/send-payment-order", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            $errorResponse = $response->json();
            $errorMessage = $errorResponse['error'] ?? 'Unknown error occurred';

            return ['error' => $errorMessage];

        } catch (\Throwable $th) {
            Log::error("Error retrieving Bithonor payment order", [
                'ticket_id' => $ticketId,
                'error' => $th->getMessage()
            ]);

            return ['error' => 'Exception occurred while retrieving payment order'];
        }
    }

    public function webhook(Request $request)
    {
        try {
            // Log raw payload
            $payload = file_get_contents("php://input");
            Log::info("Incoming webhook request from BitHonor", ['payload' => $payload]);

            // Decode JSON payload
            $data = $request->all() ?? json_decode($payload, true);

            if (!isset($data['ticket_id'], $data['status'])) {
                return response()->json(['error' => 'Invalid payload'], 400);
            }

            // Find the related payout record
            $payout = Withdraws::where('payment_gateway_id', $data['ticket_id'])->first();

            if (! $payout || $payout->status != 'pending') {
                Log::warning("BitHonor webhook: Payout not found", ['ticket_id' => $data['ticket_id']]);
                return response()->json(['error' => 'Payout completed or not found'], 404);
            }

            // Find related transaction
            $transaction = TransactionRecord::where('transaction_id', $payout->id)
                ->where('transaction_memo', 'payout')
                ->first();

            if (! $transaction) {
                Log::warning("BitHonor webhook: Transaction not found", ['payout_id' => $payout->id]);
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            // Process based on status
            if ($data['status'] === 'PAID') {
                $transaction->transaction_status = 'complete';
                $payout->status = 'complete';
            } elseif ($data['status'] === 'CANCELLED' || $data['status'] === 'FAILED') {
                $transaction->transaction_status = strtolower($data['status']);
                $payout->status = strtolower($data['status']);

                // Optional: Refund user wallet here if needed
                $user = $payout->user;
                $user->wallet->deposit($payout->amount * 100, ['description' => 'Payout refund']);
            } else {
                Log::warning("BitHonor webhook: Unknown status", ['status' => $data['status']]);
                return response()->json(['error' => 'Unknown status'], 400);
            }

            $transaction->save();
            $payout->save();

            return response()->json(['success' => true]);

        } catch (\Throwable $th) {
            Log::error("BitHonor webhook error", [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

}
