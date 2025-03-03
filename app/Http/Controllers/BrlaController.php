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

    public function webhookNotification(Request $request)
    {
        $signature = $request->header('Signature');

        if (!$signature) {
            return response()->json(['error' => 'Signature missing'], 400);
        }

        $body = $request->getContent();

        // Decode the Base64 signature
        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return response()->json(['error' => 'Invalid Base64 signature'], 500);
        }

        // Hash the request body
        $hashedBody = hash('sha256', $body, true);

        // Retrieve public key from API and cache it for 10 minutes
        $pubKey = Cache::remember('external_public_key', 600, function () {
            $response = Http::acceptJson()->get('https://api.brla.digital:5567/v1/pubkey');
            if ($response->failed()) {
                Log::error('Failed to fetch public key', ['response' => $response->body()]);
                return null;
            }
            return $response->json('publicKey');
        });

        if (!$pubKey) {
            return response()->json(['error' => 'Unable to retrieve public key'], 500);
        }

        // Convert public key to OpenSSL format
        $formattedKey = "-----BEGIN PUBLIC KEY-----\n" . 
                        trim(str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n"], '', $pubKey)) . 
                        "\n-----END PUBLIC KEY-----\n";

        $keyResource = openssl_pkey_get_public($formattedKey);
        if (!$keyResource) {
            return response()->json(['error' => 'Invalid public key format'], 500);
        }

        // Verify ECDSA signature
        $verified = openssl_verify($hashedBody, $decodedSignature, $keyResource, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Signature validated
        Log::info('Valid webhook received', ['body' => $body]);

        return response()->json(['message' => 'Webhook received successfully'], 200);
    }
}
