<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BitHonorController extends Controller
{
    public $baseUrl;
    public function __construct()
    {
        $this->baseUrl = env("BITHONOR_BASE_URL", "https://api-test.spatransfer.com");
    }
    public function sendPaymentOrder($amount)
    {
        $request = request();
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if (!$customer) {
            // use the user info since the request does not contain a customer
            $user = $request->user();
            $userName = $user->name;
            $userPhone = $user->phoneNumber;
        }
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-api-key' => env("BITHONOR_API_KEY"),
        ])->post("{$this->baseUrl}/spa/api/v1.2/send-payment-order", [
                    "secret_company_key" => env("BITHONOR_SECRET_KEY"),
                    "names" => $customer->customer_name ?? $userName,
                    "docType" => $request->docType,
                    "identNumber" => $request->docNumber,
                    "payType" => "PMV",
                    "bankCode" => $request->bankCode,
                    "currency" => "VES",
                    "amount" => $amount,
                    "phoneNumber" => $request->customer_phone ?? $userPhone,
                    "client_txid" => generate_uuid(),
                ]);

        // Return API response to the frontend
        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Request failed',
                'error' => $response->body(),
            ], $response->status());
        }
    }

    public function fetchPaymentOrder($ticketId)
    {
        try {
            $payload = [
                "secret_company_key" => "BCdgaWev",
                "filters" => [
                    "filter_field" => "ticket_id",
                    "search_field" => [
                        $ticketId
                    ]
                ]
            ];
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => env("BITHONOR_API_KEY"),
            ])->post("{$this->baseUrl}/spa/api/v1.2/send-payment-order", $payload);


            // Return API response to the frontend
            if ($response->successful()) {
                $result = $response->json();
                if(is_array($result) && isset($result['ticket_id'])) {
                    //
                }
                return ["result" => $response->json()];
            } else {
                return [
                    'error' => $response->body()
                ];
            }
        } catch (\Throwable $th) {
            Log::error("Error retrieving bithonor payment order", ['error' => $th->getMessage()]);
            return ['error' => "Error retrieving payment order"];
        }
    }
}
