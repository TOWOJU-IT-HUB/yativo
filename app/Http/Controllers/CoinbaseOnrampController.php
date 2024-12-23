<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CoinbaseOnrampController extends Controller
{
    // Method to get a session token from Coinbase API
    public function getSessionToken(Request $request)
    {
        $addresses = [
            [
                'address' => $request->input('address'), // User's wallet address
                'blockchains' => $request->input('blockchains') // List of supported blockchains
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('COINBASE_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://api.developer.coinbase.com/onramp/v1/token', [
                    'addresses' => $addresses,
                    'assets' => $request->input('assets', ['ETH', 'USDC']), // Optional list of assets
                ]);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json(['error' => 'Failed to generate session token'], 400);
    }

    // Method to generate the Onramp URL
    public function generateOnrampUrl($amount, $currency)
    {
        $request = request();
        // First, get a session token by calling the getSessionToken method
        // $tokenResponse = $this->getSessionToken($request);

        // if ($tokenResponse->status() !== 200) {
        //     return $tokenResponse;
        // }

        // $sessionToken = $tokenResponse->json()['token'];

        // Build the Onramp URL with the session token and other required parameters
        $url = 'https://pay.coinbase.com/buy/select-asset?' . http_build_query([
            'appId' => env('COINBASE_APP_ID'),
            'partnerUserId' => $request->input('partnerUserId', null), // Unique user identifier
            'addresses' => json_encode([
                $request->input('address', '0x59a8f26552CaF6ea7F669872bf39443d8d0eFB96') => ['base']
            ]),
            'presetFiatAmount' => $request->amount,
            'fiatCurrency' => $currency,
            'assets' => json_encode($request->input('assets', ['USDB', 'ETH'])),
            'sessionToken' => $sessionToken ?? null,
            'redirectUrl' => $request->input('redirectUrl', env('WEB_URL', 'https://your-app.com/redirect')),
        ]);

        return response()->json([
            'onramp_url' => $url
        ]);
    }
}
