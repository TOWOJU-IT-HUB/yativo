<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class MantecaController extends Controller
{
    protected $baseUrl = 'https://sandbox.manteca.dev/crypto/v1/';
    protected $headers = [
        'Content-Type' => 'application/json',
        'md-api-key' => env('MANTECA_API_KEY')
    ];

    public function __construct()
    {
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
            'state' => 'requied',
            "customer_id" => "required|exists:customers,customer_id",
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', request('customer_id'))->first();
        $payload = [
            "name" => $customer->customer_name,
            "email" => $customer->customer_email,,
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
        if(isset($result["numberId"])) {
            $customer->update([
                "manteca_user_id" => $result["numberId"]
            ]);

            return get_success_response(['message' => "customer enrolled successfully"]);
        }
        return get_error_response(['error' => "Unable to enroll customer"]);
    }

    public function getUploadUrl($userId, $docType = 'DNI_BACK', $fileName = 'passport.jpg')
    {
        $payload = [
            'docType' => $docType,
            'fileName' => $fileName
        ];

        $response = Http::withHeaders($this->headers)
            ->post("{$this->baseUrl}documentation/{$userId}/uploadUrl", $payload);

        if ($response->successful()) {
            return $response->json()['uploadUrl'] ?? null;
        }

        throw new \Exception("Failed to get upload URL. Error: " . $response->body());
    }


    public function uploadToS3(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'docType' => 'required',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf',
            "customer_id" => "required|exists:customers,customer_id",
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();
        $userId = $customer->manteca_user_id;
        $document = $request->file('file');
        $filename = $document->getClientOriginalName();
        $mimeType = $document->getMimeType();

        try {
            $uploadUrl = $this->getUploadUrl($userId, $request->docType, $filename);
            if (!$uploadUrl) {
                return get_error_response(['error' => 'Upload URL not received'], 500);
            }

            $response = Http::withHeaders([
                'Content-Type' => $mimeType
            ])->put($uploadUrl, [$filename => file_get_contents($document)]);

            return get_success_response($response->body(),  $response->status());
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 500);
        }
    }


    public function getPriceRate($userId = '100007696')
    {
        $payload = [
            "coin" => "USDT_ARS",
            "operation" => "BUY",
            "userId" => $userId
        ];

        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'order/lock', $payload);

        return $response->json();
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,customer_id',
            'amount' => 'required|numeric|min:1',
            'coin' => 'required|string',
            // 'operation' => 'required|in:BUY,SELL'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        $ratePayload = [
            "coin" => $request->coin,
            "operation" => "BUY",
            "userId" => $customer->manteca_user_id
        ];

        $code = $this->getPriceRate($ratePayload);

        $payload = [
            "userId" => $customer->manteca_user_id,
            "amount" => $request->amount,
            "coin" => $request->coin,
            "operation" => "BUY",
            "code" => $code['code']
        ];

        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'order', $payload);

        
        if($response->status() == 200) {
            return get_success_response($response->json());
        }
        return get_error_response(['error' => 'Unable to process request, try again later or contact support']);
    }

    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,customer_id',
            'coin' => 'required|string|in:ARS,USD,EUR', // Add other allowed fiat currencies if needed
            'cbu' => 'required|string',
            'amount' => 'required|numeric|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        $payload = [
            'userId' => $customer->manteca_user_id,
            'coin' => $request->coin,
            'cbu' => $request->cbu,
            'amount' => $request->amount
        ];

        $response = Http::withHeaders($this->headers)
                    ->post($this->baseUrl .'fiat/withdraw', $payload);

        if($response->status() == 200) {
            return get_success_response($response->json());
        }
        return get_error_response(['error' => 'Unable to process request, try again later or contact support']);
    }
}
