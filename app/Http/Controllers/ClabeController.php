<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ClabeService;
use Log;

class ClabeController extends Controller
{
    public function generateClabes(ClabeService $clabeService)
    {
        $results = [];

        for ($i = 0; $i < 5; $i++) {
            $clabe = $clabeService->generateNextClabe();
            $validation = $clabeService->validate($clabe);
            $results[] = [
                'clabe' => $clabe,
                'status' => $validation['ok'] ? 'Valid' : 'Invalid',
                'details' => $validation,
            ];
        }

        return response()->json($results);
    }

    // handle all stp-mx payouts
    public function handlePayout(Request $request)
    {
        $data = $request->all();


        // Validate and store/update payout info
        Log::info('Payout Webhook Received', $data);


        // Return acknowledgment
        return response()->json(['message' => 'Webhook received'], 200);
    }


    // handle all payin into the virtual account from stp.mx
    public function handleDeposit(Request $request)
    {
        $data = $request->all();


        if ($this->isSuspicious($data)) {
            return response()->json(['error' => 'Invalid amount or suspicious transaction'], 402);
        }


        // Store deposit
        Log::info('Deposit Received', $data);


        return response()->json(['message' => 'Webhook received'], 200);
    }


    private function isSuspicious($data)
    {
        if(!isset($data) || !isset($data['type']) || $data['type'] !== "SPEI") {
            return true;
        }
        return $data['monto'] <= 0 || !in_array($data['type'], ['SPEI']);
    }


    public function createPayOutOrder(Request $request)
    {
        try {
            $url = "https://demo.stpmex.com:7024/speiws/rest/ordenPago/registra";
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}