<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OnrampService;

class OnrampController extends Controller
{
    protected $onrampService;

    public function __construct(OnrampService $onrampService)
    {
        $this->onrampService = $onrampService;
    }

    public function getQuotes(Request $request)
    {
        $data = $request->all();
        $response = $this->onrampService->getQuotes($data);
        return response()->json($response);
    }

    /**
     * Deposit money to a customer buy inititating a buy command
     */
    public function payIn(Request $request)
    {
        $data = $request->all();
        $response = $this->onrampService->payIn($data);
        return response()->json($response);
    }

    /**
     * Pay out to a customer buy inititating a sell command
     */
    public function payOut(Request $request)
    {
        $data = $request->all();
        $response = $this->onrampService->payOut($data);
        return response()->json($response);
    }
}
