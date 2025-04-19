<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\Customer;

class MantecaController extends Controller
{
    protected $baseUrl = 'https://sandbox.manteca.dev/crypto/v1/';
    protected $headers;

    public function __construct()
    {
        $this->headers = [
            'Content-Type' => 'application/json',
            'md-api-key' => env('MANTECA_API_KEY'),
        ];

        $this->baseUrl =  env('MANTECA_BASE_URL', 'https://sandbox.manteca.dev/crypto/v1/');

        if (!Schema::hasColumn('customers', 'manteca_user_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('manteca_user_id')->nullable();
            });
        }
    }

    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'legalId' => 'required',
            'country' => 'required',
            'state' => 'required',
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        $payload = [
            "name" => $customer->customer_name,
            "email" => $customer->customer_email,
            "legalId" => $request->legalId,
            "phoneNumber" => $customer->customer_phone,
            "country" => $request->country,
            "civilState" => $request->state,
            "externalId" => generate_uuid(),
            "isPep" => false,
            "isFatca" => false,
            "isUif" => false
        ];

        $response = Http::withHeaders($this->headers)->post($this->baseUrl . 'user', $payload);
        $result = $response->json();

        if (isset($result["numberId"])) {
            $customer->update([
                "manteca_user_id" => $result["numberId"]
            ]);

            return get_success_response(['message' => "Customer enrolled successfully"]);
        }
        Log::debug("Error creating Manteca user: ", ['response' => $response]);
        return get_error_response(['error' => "Unable to enroll customer"]);
    }

    public function getUploadUrl($userId, $docType = 'DNI_BACK', $fileName = 'passport.jpg')
    {
        $payload = [
            'docType' => $docType,
            'fileName' => $fileName
        ];

        $finalUrl = "{$this->baseUrl}documentation/{$userId}/uploadUrl";
        
        $query = Http::withHeaders($this->headers)
            ->post($finalUrl, $payload);

        $response = $query->json();

        if (isset($response['url']) && null !== $response['url']) {
            return $response['url'] ?? null;
        }

        Log::debug("Error creating Manteca user: ", ['response' => $response]);
        return ['error' => "Failed to get upload URL. Error: " . $response];
    }

    public function uploadToS3(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'docType' => 'required|string|in:DNI_FRONT,DNI_BACK,FUNDS,BALANCE,ESTATUTO,IIBB,ACTA_DESIGNACION,ULTIMO_BALANCE_CERTIFICADO,CONSTANCIA_DE_CUIT,EXTRA,PEP_DDJJ,SUJETO_OBLIGADO_DDJJ,SELFIE,CERTIFICACION_CONTABLE,IMPUESTO_CEDULAR,PRIVATE_REPORT',
            'document' => 'required|file|mimes:jpg,jpeg,png',
            'fileName' => 'required',
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();
        $document = $request->file('document');
        $filename = $request->fileName;
        $mimeType = $document->getMimeType();

        try {
            // upload document front
            $uploadUrl = $this->getUploadUrl($customer->manteca_user_id, $request->docType, $request->fileName);
            if (!$uploadUrl) {
                return get_error_response(['error' => 'Upload URL not received'], 500);
            }

            Log::info("Upload url is: ", ['url' => $uploadUrl]);
            
            
            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => $uploadUrl,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'PUT',
              CURLOPT_POSTFIELDS => array($request->fileName => $request->document),
              CURLOPT_HTTPHEADER => []
            ));
            
            $response = curl_exec($curl);
            
            curl_close($curl);
            if($response) {
                $response = (array)$response;
            }


            return get_success_response(['message' => 'File uploaded successfully', 'response' => $response]);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 500);
        }
    }

    public function getPriceRate($payload)
    {
        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'order/lock', $payload);

        Log::debug("Error generating price rate: ", ['response' => $response->json()]);
        return $response->json();
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,customer_id',
            'amount' => 'required|numeric|min:1',
            'coin' => 'required|string',
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        $asset = explode("_", $request->coin);
        $ratePayload = [
            "coin" => $request->coin,
            "operation" => "BUY",
            "userId" => "100007696" // $customer->manteca_user_id
        ];

        $codeResponse = $this->getPriceRate($ratePayload);
        Log::debug("Error generating deposit lock: ", ['response' => $codeResponse]);

        if (!isset($codeResponse['code'])) {
            return get_error_response(['error' => 'Unable to retrieve rate code']);
        }

        // [
        //     "userId" => "100007696", //$customer->manteca_user_id,
        //     "amount" => $request->amount,
        //     "coin" => $request->coin,
        //     "operation" => "BUY",
        //     "code" => $codeResponse['code']
        // ];

        $payload = [
            "externalId" => generate_uuid(),
            "userAnyId" => "100007696", //$customer->manteca_user_id,
            "sessionId" => generate_uuid(),
            "asset" => $asset[0],
            "against" => $asset[1],
            "assetAmount" => $request->amount,
            "priceCode" => $codeResponse['code'],
            "withdrawAddress" => "0x9C2d7ccA1d1023B2038d91196ea420d731226f73",
            "withdrawNetwork" => "BASE"
        ];

        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'order', $payload);

        if ($response->ok()) {
            Log::debug("Manteca deposit details: ", ['response' => $response->json()]);
            return get_success_response($response->json());
        }


        Log::debug("Error creating Manteca deposit: ", ['response' => $response]);
        return get_error_response(['error' => 'Unable to process order']);
    }

    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,customer_id',
            'coin' => 'required|string|in:ARS,USD,EUR',
            'cbu' => 'required|string',
            'amount' => 'required|numeric|min:1'
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        $payload = [
            'userId' => "100007696", //$customer->manteca_user_id,
            'coin' => $request->coin,
            'cbu' => $request->cbu,
            'amount' => $request->amount
        ];

        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'fiat/withdraw', $payload);

        if ($response->ok()) {
            return get_success_response($response->json()[0]);
        }

        Log::debug("Error creating Manteca payout: ", ['response' => $response]);
        return get_error_response(['error' => 'Unable to process withdrawal']);
    }
}
