<?php

namespace Modules\Webhook\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;

class BitsoWebhookController extends Controller
{
    /**
     * Handle incoming webhook requests from Bitso.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request)
    {
        // Log the entire payload for debugging purposes
        Log::info('Bitso Webhook Received: ', $request->all());

        // Validate the required fields
        $validatedData = $request->validate([
            'event' => 'required|string',
            'payload' => 'required|array',
            'payload.status' => 'required|string',
        ]);

        $eventType = $validatedData['event'];
        $payload = $validatedData['payload'];
        $status = strtolower($payload['status']); // Normalize status to lowercase

        // Handle different events
        switch ($eventType) {
            case 'funding':
                return $this->handleFundingEvent($payload, $status);
            case 'withdrawal':
                return $this->handleWithdrawalEvent($payload, $status);
            case 'dynamic_qr_code':
                return $this->handleDynamicQrCodeEvent($payload, $status);
            default:
                Log::warning("Unknown event type received: $eventType");
                return response()->json(['message' => 'Unknown event type'], 400);
        }
    }

    /**
     * Handle funding event.
     *
     * @param  array  $payload
     * @param  string $status
     * @return \Illuminate\Http\Response
     */
    private function handleFundingEvent($payload, $status)
    {
        if ($status === 'complete') {
            Log::info('Funding completed: ', $payload);
            // Process completed funding transaction, such as updating the user's balance
        } elseif ($status === 'failed') {
            Log::error('Funding failed: ', $payload);
            // Handle failed funding transaction, such as notifying the user
        } else {
            Log::info("Funding status: $status", $payload);
        }

        // Additional handling for CLABE funding details
        $details = $payload['details'] ?? [];
        if (isset($details['receive_clabe'])) {
            Log::info('CLABE Funding Details:', [
                'sender_name' => $details['sender_name'] ?? '',
                'sender_clabe' => $details['sender_clabe'] ?? '',
                'receive_clabe' => $details['receive_clabe'] ?? '',
                'sender_bank' => $details['sender_bank'] ?? '',
                'clave_rastreo' => $details['clave_rastreo'] ?? '',
                'numeric_reference' => $details['numeric_reference'] ?? '',
                'concepto' => $details['concepto'] ?? '',
            ]);

            // Additional logic for processing CLABE funding, if needed
            // For example, you could notify the user based on receive_clabe
        }

        return response()->json(['message' => 'Funding event processed'], 200);
    }

    /**
     * Handle withdrawal event.
     *
     * @param  array  $payload
     * @param  string $status
     * @return \Illuminate\Http\Response
     */
    private function handleWithdrawalEvent($payload, $status)
    {
        if ($status === 'complete') {
            Log::info('Withdrawal completed: ', $payload);
            // Process completed withdrawal transaction
        } elseif ($status === 'failed') {
            Log::error('Withdrawal failed: ', $payload);
            $failReason = $payload['details']['fail_reason'] ?? 'Unknown reason';
            Log::error("Withdrawal failure reason: $failReason");
            // Handle failed withdrawal transaction, such as notifying the user
        } else {
            Log::info("Withdrawal status: $status", $payload);
        }

        // Additional details for withdrawal
        $details = $payload['details'] ?? [];
        Log::info('Withdrawal Details:', $details);

        return response()->json(['message' => 'Withdrawal event processed'], 200);
    }

    /**
     * Handle dynamic QR code event.
     *
     * @param  array  $payload
     * @param  string $status
     * @return \Illuminate\Http\Response
     */
    private function handleDynamicQrCodeEvent($payload, $status)
    {
        if ($status === 'expired') {
            Log::warning('Dynamic QR Code expired: ', $payload);
            // Handle expired QR code, such as notifying the user or retrying
        } elseif ($status === 'complete') {
            Log::info('Dynamic QR Code payment completed: ', $payload);
            // Process completed QR code transaction
        } else {
            Log::info("Dynamic QR Code status: $status", $payload);
        }

        // Additional details for dynamic QR code
        $payer = $payload['payer'] ?? [];
        $qrCodePayload = $payload['qr_code_payload'] ?? '';
        Log::info('Payer Details:', $payer);
        Log::info('QR Code Payload:', ['qr_code_payload' => $qrCodePayload]);

        return response()->json(['message' => 'Dynamic QR Code event processed'], 200);
    }
}
