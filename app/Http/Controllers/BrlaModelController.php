<?php

namespace App\Http\Controllers;

use App\Services\BrlaApiService;
use Illuminate\Http\Request;

class BrlaModelController extends Controller
{
    protected $brlaApi;

    public function __construct(BrlaApiService $brlaApi)
    {
        $this->brlaApi = $brlaApi;
    }

    public function generateBrCode(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'referenceLabel' => 'required|string',
        ]);

        return $this->brlaApi->generateBrCode($request->amount, $request->referenceLabel);
    }

    public function closePixToUsd(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'markupAddress' => 'required|string',
            'receiverAddress' => 'required|string',
            'externalId' => 'required|string',
        ]);

        return $this->brlaApi->closePixToUsd($request->token, $request->markupAddress, $request->receiverAddress, $request->externalId);
    }

    public function closePixToToken(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'token' => 'required|string',
            'markup' => 'required|string',
            'receiverAddress' => 'required|string',
            'markupAddress' => 'required|string',
            'referenceLabel' => 'required|string',
            'externalId' => 'required|string',
        ]);

        return $this->brlaApi->closePixToToken(
            $request->amount,
            $request->token,
            $request->markup,
            $request->receiverAddress,
            $request->markupAddress,
            $request->referenceLabel,
            $request->externalId
        );
    }

    /**
     * Create pay-out order with inputed information. 
     * pixKey is either the key associated to a bank account, or a BR Code.
     * 
     * 
     */
    public function createPayOutOrder(Request $request)
    {
        $request->validate([
            'pixKey' => 'required|string',
            'amount' => 'nullable|numeric',
            'externalId' => 'required|string',
            'name' => 'required|string',
            'ispb' => 'required|string',
            'branchCode' => 'required|string',
            'accountNumber' => 'required|string',
            'taxId' => 'nullable|string',
        ]);

        $request = $this->brlaApi->createPayOutOrder($request->all());
        if(isset($request['id'])) {
            return get_success_response(['message' => 'Payout request recieved and will be proccessed shortly', 'data' => $request]);
        }
    }

    /**
     * USD to PIX Payout system
     */
    public function createUsdToPixOrder(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'pixKey' => 'required|string',
            'taxId' => 'required|string',
            'externalId' => 'required|string',
            'name' => 'required|string',
            'ispb' => 'required|string',
            'branchCode' => 'required|string',
            'accountNumber' => 'required|string',
        ]);

        return $this->brlaApi->createUsdToPixOrder($request->all());
    }

    public function convertCurrencies(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'receiverAddress' => 'required|string',
            'markupAddress' => 'required|string',
            'externalId' => 'required|string',
            'enforceAtomicSwap' => 'required|boolean',
        ]);

        return $this->brlaApi->convertCurrencies($request->all());
    }
}
