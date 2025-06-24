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

    public function handlePayout(Request $request)
    {
        Log::info('Payout Webhook Received', ['data' => $request->all()]);

        $validated = $request->validate([
            'id' => 'required|string|max:255',
            'estado' => 'required|string|max:10',
        ]);

        $payoutId = $validated['id'];
        $estado = strtoupper(trim($validated['estado'])); // Normalize and sanitize

        // $cache0 = cache()->get("YATIVTRANSID0");
        // $cache1 = cache()->get("YATIVTRANSID1");

        // // Override estado if ID matches cached ones
        // if ($payoutId === $cache0) {
        //     $estado = 'CN';
        // } elseif ($payoutId === $cache1) {
        //     $estado = 'D';
        // }
        
        $response = 'recibido';

        // switch ($estado) {
        //     case 'LQ': // Liquidated successfully
        //         Log::info("Payout ID {$payoutId} marked as SUCCESS");
        //         $response = 'recibido';
        //         break;

        //     case 'CN': // Cancelled
        //         Log::warning("Payout ID {$payoutId} CANCELLED");
        //         $response = 'cancelación';
        //         break;

        //     case 'D': // Refunded
        //         Log::warning("Payout ID {$payoutId} REFUNDED");
        //         $response = 'devolución';
        //         break;

        //     default:
        //         Log::error("Unknown estado '{$estado}' for payout ID {$payoutId}");
        //         return response()->json([
        //             'id' => $payoutId,
        //             'mensaje' => 'estado desconocido',
        //         ], 400);
        // }

        return response()->json([
            // 'id' => $payoutId,
            'mensaje' => $response,
        ], 200);
    }


    // handle all payin into the virtual account from stp.mx
    public function handleDeposit(Request $request)
    {
        $data = $request->all();

        if (!isset($request->monto)) {
            return response()->json(['error' => 'Monto not found in payload'], 422);
        }

        // Mapping of reason codes to rejection messages
        $rejectionReasons = [
            1 => 'Cuent inexi',
            2 => 'Cuent bloqu',
            3 => 'Cuent cance',
            5 => 'Cuent en otra divis',
            6 => 'Cuent no perte al ba',
            14 => 'Falta infor manda pa',
            15 => 'Tipo de pago erron',
            16 => 'Tipo de opera erron',
            17 => 'Tipo de cuent no cor',
            19 => 'Carac Invál',
            20 => 'Exced el límit de sa',
            21 => 'Exced el límit de ab',
            22 => 'Númer de línea de te',
            23 => 'Cuent adici no recib',
            24 => 'Estru de la infor ad',
            25 => 'Falta de instr para',
            26 => 'Resol resul del Conv',
            27 => 'Pago opcio no acept',
            28 => 'Tipo de pago CoDi si',
            30 => 'Clave de rastr repet',
            31 => 'Cert emisor vencido',
        ];

        Log::info('Deposit Received', $data);

        // Check if monto exactly matches any rejection code
        $monto = (int) $request->monto;
        if (array_key_exists($monto, $rejectionReasons)) {
            return response()->json([
                'mensaje' => 'devolver',
                'id' => $monto
            ], 400);
        }

        // Check if a separate reason_code param is provided
        if (isset($request->reason_code) && array_key_exists($request->reason_code, $rejectionReasons)) {
            return response()->json([
                'mensaje' => 'devolver',
                'id' => $request->reason_code
            ], 400);
        }

        // Accept the payment
        return response()->json([
            'mensaje' => 'confirmar'
        ], 200);
    }


    private function isSuspicious($data)
    {
        return false;
        // if(!isset($data)) {
        //     return true;
        // }

        // return $data['monto'] <= 0;
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