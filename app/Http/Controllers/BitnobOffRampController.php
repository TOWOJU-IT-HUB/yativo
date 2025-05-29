<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;

class BitnobOffRampController extends Controller
{
    protected $baseUrl;
    protected $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.bitnob.base_url');
        $this->secretKey = config('services.bitnob.secret_key');
    }

    public function createQuote(Request $request)
    {
        $validated = $request->validate([
            'fromAsset' => 'required|string',
            'toCurrency' => 'required|string',
            'source' => 'required|string|in:onchain',
            'chain' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/api/v1/payouts/quotes", $validated);

            if ($response->successful()) {
                return get_success_response($response->json('data'));
            }

            return get_error_response($response->json());

        } catch (\Throwable $e) {
            return get_error_response([
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function initializePayout(Request $request)
    {
        $validated = $request->validate([
            'quoteId' => 'required|string',
            'customerId' => 'required|string',
            'country' => 'required|string',
            'reference' => 'required|string',
            'paymentReason' => 'required|string',
            'callbackUrl' => 'required|url',
            'clientMetaData' => 'nullable|array',
            'beneficiaryId' => 'nullable|string',
            'beneficiary' => 'nullable|array',
            'beneficiary.type' => 'required_without:beneficiaryId|string|in:BANK',
            'beneficiary.bankCode' => 'required_without:beneficiaryId|string',
            'beneficiary.accountNumber' => 'required_without:beneficiaryId|string',
        ]);

        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/api/v1/payouts/initialize", $validated);

            if ($response->successful()) {
                return get_success_response($response->json('data'));
            }

            return get_error_response($response->json());

        } catch (\Throwable $e) {
            return get_error_response([
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function finalizePayout(Request $request)
    {
        $validated = $request->validate([
            'quoteId' => 'required|string',
        ]);

        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/api/v1/payouts/finalize", $validated);

            if ($response->successful()) {
                return get_success_response($response->json('data'));
            }

            return get_error_response($response->json());

        } catch (\Throwable $e) {
            return get_error_response([
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
