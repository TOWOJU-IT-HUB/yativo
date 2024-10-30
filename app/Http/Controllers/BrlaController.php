<?php

namespace App\Http\Controllers;

use App\Services\BrlaDigitalService;
use Illuminate\Http\Request;

class BrlaController extends Controller
{
    private $brlaService;

    public function __construct(BrlaDigitalService $brlaService)
    {
        $this->brlaService = $brlaService;
    }

    public function generatePayInBRCode()
    {
        return $this->brlaService->generatePayInBRCode();
    }

    public function getPayInHistory()
    {
        return $this->brlaService->getPayInHistory();
    }

    /**
     * @param string token
     * @param string markupAddress
     * @param string receiverAddress
     * @param string externalId
     * 
     * @return mixed
     */
    public function closePixToUSDDeal($data)
    {
        return $this->brlaService->closePixToUSDDeal($data);
    }

    /**
     * @param string token
     * @param string receiverAddress
     * @param string markupAddress
     * @param string externalId
     * @param string enforceAtomicSwap
     * 
     * @return mixed
     */
    public function convertCurrencies($data)
    {
        return $this->brlaService->convertCurrencies($data);
    }
}
