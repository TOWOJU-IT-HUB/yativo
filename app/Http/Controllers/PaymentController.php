<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;

class PaymentController extends Controller
{
    public function registerPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'claveRastreo' => 'required|string|max:30',
            'conceptoPago' => 'required|string|max:40',
            'cuentaOrdenante' => 'required|string|max:20',
            'cuentaBeneficiario' => 'required|string|max:20',
            'empresa' => 'required|string|max:15',
            'institucionContraparte' => 'required|string|max:5',
            'institucionOperante' => 'required|string|max:5',
            'monto' => 'required|numeric|max:999999999999.99',
            'nombreBeneficiario' => 'required|string|max:40',
            'nombreOrdenante' => 'required|string|max:40',
            'referenciaNumerica' => 'required|string|max:7',
            'rfcCurpBeneficiario' => 'required|string|max:18',
            'rfcCurpOrdenante' => 'required|string|max:18',
            'tipoCuentaBeneficiario' => 'required|string|max:2',
            'tipoCuentaOrdenante' => 'required|string|max:2',
            'tipoPago' => 'required|string|max:2',
            'latitud' => 'required|string|max:30',
            'longitud' => 'required|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Generate the signature
        if(env('IS_DEMO_STP') || env('IS_DEMO')) {
            $privateKeyPath = storage_path('app/keys/stp_demo.pem');
            $passphrase = '12345678';
            $url = "https://demo.stpmex.com:7024/speiws/rest/ordenPago/registra";
        } else {
            $privateKeyPath = storage_path('app/yativo.pem');
            $passphrase = '1234567890';
            $url = "https://prod.stpmex.com:7002/speiws/rest/ordenPago/registra";
        }
        $signature = $this->generateSignature($data, $privateKeyPath, $passphrase);

        // Prepare final request payload
        $requestData = array_merge($data, ['firma' => $signature]);

        // Make API call
        $client = new Client();
        $response = $client->put($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Encoding' => 'UTF-8',
            ],
            'json' => $requestData,
        ]);

        return response()->json(json_decode($response->getBody(), true), $response->getStatusCode());
    }

    private function generateSignature(array $data, string $privateKeyPath, string $passphrase): string
    {
        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath), $passphrase);

        if (!$privateKey) {
            throw new \Exception('Failed to load private key');
        }

        $originalString = '||' .
            $data['institucionContraparte'] . '|' .
            $data['empresa'] . '|||' .
            $data['claveRastreo'] . '|' .
            $data['institucionOperante'] . '|' .
            number_format($data['monto'], 2, '.', '') . '|' .
            $data['tipoPago'] . '|' .
            $data['tipoCuentaOrdenante'] . '|' .
            $data['nombreOrdenante'] . '|' .
            $data['cuentaOrdenante'] . '|' .
            $data['rfcCurpOrdenante'] . '|' .
            $data['tipoCuentaBeneficiario'] . '|' .
            $data['nombreBeneficiario'] . '|' .
            $data['cuentaBeneficiario'] . '|' .
            $data['rfcCurpBeneficiario'] . '||||||' .
            $data['conceptoPago'] . '||||||' .
            $data['referenciaNumerica'] . '||||||||||';

        $binarySignature = '';
        $success = openssl_sign($originalString, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        if (!$success) {
            throw new \Exception('Failed to generate digital signature');
        }

        return base64_encode($binarySignature);
    }

}
