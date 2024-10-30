<?php

namespace App\Http\Controllers;

use App\Models\BitsoWebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Log the request for debugging
        Log::info('Received Bitso Webhook:', $request->all());

        $payload = $request->input('payload');
        
        // Check if the webhook has already been processed (by fid)
        if (BitsoWebhookLog::where('fid', $payload['fid'])->exists()) {
            return response()->json(['message' => 'Transaction already processed'], 200);
        }

        // Create a new BitsoWebhookLog entry
        BitsoWebhookLog::create([
            'fid' => $payload['fid'],
            'status' => $payload['status'],
            'currency' => $payload['currency'],
            'method' => $payload['method'],
            'method_name' => $payload['method_name'],
            'amount' => $payload['amount'],
            'details' => $payload['details'],
        ]);

        // Return a successful response
        return response()->json(['message' => 'Webhook processed'], 200);
    }
}
