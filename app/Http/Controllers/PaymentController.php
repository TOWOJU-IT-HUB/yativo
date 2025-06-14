<?php

namespace App\Http\Controllers;

use App\Services\STPSign;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    private $isDemo;

    public function __construct()
    {
        $this->isDemo = env('IS_STP_DEMO', true);
    }

    public function registerPayment(Request $request)
    {
        $data = [
            "claveRastreo"           => "Pruebayativo" . mt_rand(0, 9999),
            "conceptoPago"           => "Prueba REST",
            "cuentaOrdenante"        => "646180610900000007",
            "cuentaBeneficiario"     => "646180209100000001",
            "empresa"                => "YATIVO",
            "institucionContraparte" => "90646",
            "institucionOperante"    => "90646",
            "monto"                  => "0.01",
            "nombreBeneficiario"     => "S.A. de C.V.",
            "nombreOrdenante"        => "S.A. de C.V.",
            "referenciaNumerica"     => "123456",
            "rfcCurpBeneficiario"    => "ND",
            "rfcCurpOrdenante"       => "ND",
            "tipoCuentaBeneficiario" => "40",
            "tipoCuentaOrdenante"    => "40",
            "tipoPago"               => "1",
            "latitud"                => "19.370312",
            "longitud"               => "-99.180617",
        ];

        $privateKeyPath = storage_path('app/keys/stp_demo.pem'); 
        $passphrase = '12345678';

        $stp = new STPSign($data, $privateKeyPath, $passphrase);

        $originalString = $stp->getCadenaOriginal();
        $signature = $stp->getSign();
        $requestData = array_merge($data, ['firma' => $signature]);

        $url = "https://demo.stpmex.com:7024/speiws/rest/ordenPago/registra";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Encoding'     => 'UTF-8',
            ])->put($url, $requestData);

            // Get response JSON as array (Laravel parses JSON automatically)
            $responseData = $response->json();

            // Get HTTP status code
            $status = $response->status();

            return get_success_response($responseData, $status);
        } catch (\Exception $e) {
            return get_error_response([
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    public function payout($data)
    {
        $rules = [
            'cuentaOrdenante'        => 'required',
            'nombreOrdenante'        => 'required',
            'rfcCurpOrdenante'       => 'required',
            'tipoCuentaOrdenante'    => 'required',
            'cuentaBeneficiario'     => 'required',
            'nombreBeneficiario'     => 'required',
            'rfcCurpBeneficiario'    => 'required',
            'tipoCuentaBeneficiario' => 'required',
            'institucionContraparte' => 'required',
            'empresa'                => 'required',
            'claveRastreo'           => 'required',
            'institucionOperante'    => 'required',
            'monto'                  => 'required|numeric|min:0.01',
            'tipoPago'               => 'required',
            'conceptoPago'           => 'required',
            'referenciaNumerica'     => 'required',
            'latitud'                => 'nullable|numeric',
            'longitud'               => 'nullable|numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return [
                'errors' => $validator->errors()
            ];
        }

        $data = $validator->validated();

        $privateKeyPath = storage_path('app/keys/yativo.pem');
        $passphrase = '1234567890';

        $stp = new STPSign($data, $privateKeyPath, $passphrase);

        $originalString = $stp->getCadenaOriginal();
        $signature = $stp->getSign();
        $requestData = array_merge($data, ['firma' => $signature]);

        $url = "https://prod.stpmex.com:7002/speiws/rest/ordenPago/registra";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Encoding'     => 'UTF-8',
            ])->put($url, $requestData);

            // Get response JSON as array (Laravel parses JSON automatically)
            $responseData = $response->json();

            // Get HTTP status code
            $status = $response->status();

            return get_success_response($responseData, $status);
        } catch (\Exception $e) {
            return get_error_response([
                'error' => $e->getMessage(),
            ], 500);
        }

    }
}
