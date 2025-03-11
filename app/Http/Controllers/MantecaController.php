<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MantecaController extends Controller
{
    private $apiKey = 'API_KEY';
    private $baseUrl = 'https://api.manteca.dev/crypto/v1/user/';

    public function createUser(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'legalId' => 'required|string',
            'phoneNumber' => 'required|string',
            'country' => 'required|string',
            'civilState' => 'required|string',
            'externalId' => 'required|string',
            'isPep' => 'required|boolean',
            'isFatca' => 'required|boolean',
            'isUif' => 'required|boolean',
        ]);

        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl, $data);

        return $response->json();
    }

    public function getUser($id)
    {
        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . $id);

        return $response->json();
    }

    public function getUserOrders($userId)
    {
        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . $userId . '/orders');

        return $response->json();
    }

    public function addBankAccount(Request $request, $userId, $currency)
    {
        $data = $request->validate([
            'cbu' => 'required|string',
            'description' => 'required|string',
        ]);

        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . $userId . '/bankaccount/' . $currency, $data);

        return $response->json();
    }

    public function deleteBankAccount($userId, $currency, $cbu)
    {
        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->delete($this->baseUrl . $userId . '/bankaccount/' . $currency . '/' . $cbu);

        return $response->json();
    }



    public function makeWithdrawal(Request $request)
    {
        $data = $request->validate([
            'userId' => 'required|string',
            'coin' => 'required|string',
            'cbu' => 'required|string',
            'amount' => 'required|string',
        ]);

        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . 'fiat/withdraw', $data);

        return $response->json();
    }

    public function getWithdrawalById($id)
    {
        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . 'fiat/withdraw/' . $id);

        return $response->json();
    }

    public function getWithdrawals(Request $request)
    {
        $userId = $request->query('userId');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $query = http_build_query([
            'userId' => $userId,
            'page' => $page,
            'limit' => $limit,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . 'fiat/withdraw/?' . $query);

        return $response->json();
    }

    public function getDeposits(Request $request)
    {
        $userId = $request->query('userId');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $query = http_build_query([
            'userId' => $userId,
            'page' => $page,
            'limit' => $limit,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . 'fiat/deposit/?' . $query);

        return $response->json();
    }

    public function createWithdrawalLock(Request $request)
    {
        $data = $request->validate([
            'coin' => 'required|string',
            'userId' => 'required|string',
            'chain' => 'required|string',
        ]);

        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . 'transaction/withdraw/lock', $data);

        return $response->json();
    }

    public function createWithdrawal(Request $request)
    {
        $data = $request->validate([
            'tx' => 'required|array',
            'tx.coin' => 'required|string',
            'tx.amount' => 'required|string',
            'tx.to' => 'required|string',
            'tx.chain' => 'required|string',
            'userId' => 'required|string',
            'costCode' => 'required|string',
        ]);

        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . 'transaction/withdraw', $data);

        return $response->json();
    }

    public function getSupportedAssets()
    {
        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . 'transaction/supported-assets');

        return $response->json();
    }

    public function getTransactions(Request $request)
    {
        $userId = $request->query('userId');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        $type = $request->query('type');
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $query = http_build_query([
            'userId' => $userId,
            'page' => $page,
            'limit' => $limit,
            'type' => $type,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . 'transaction/?' . $query);

        return $response->json();
    }

    public function getTransactionById($id)
    {
        $response = Http::withHeaders([
            'md-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . 'transaction/' . $id);

        return $response->json();
    }
}