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

        $response = Http::withHeaders($this->headers)
            ->post("{$this->baseUrl}documentation/{$userId}/uploadUrl", $payload);

        if ($response->successful()) {
            return $response->json()['uploadUrl'] ?? null;
        }

        Log::debug("Error creating Manteca user: ", ['response' => $response]);
        return ['error' => "Failed to get upload URL. Error: " . $response->body()];
    }

    public function uploadToS3(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'docType' => 'required|string',
            'document_front' => 'required|file|mimes:jpg,jpeg,png,pdf',
            'document_back' => 'required|file|mimes:jpg,jpeg,png,pdf',
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();
        $document_front = $request->file('document_front');
        $filename = $document_front->getClientOriginalName();
        $mimeType = $document_front->getMimeType();

        try {
            // upload document front
            $uploadUrl = $this->getUploadUrl($customer->manteca_user_id, $request->docType."FRONT", $filename);
            if (!$uploadUrl) {
                return get_error_response(['error' => 'Upload URL not received'], 500);
            }

            Log::info("Upload url is: ", ['url' => $uploadUrl]);
            $response = Http::withHeaders([
                'Content-Type' => $mimeType,
            ])->put($uploadUrl['url'], file_get_contents($document_front));


            // upload document back

            $document_back = $request->file('document_front');
            $filename = $document_back->getClientOriginalName();
            $mimeType = $document_back->getMimeType();

            $uploadUrl = $this->getUploadUrl($customer->manteca_user_id, $request->docType."_BACK", $filename);
            if (!$uploadUrl) {
                return get_error_response(['error' => 'Upload URL not received'], 500);
            }
            Log::info("Upload url is: ", ['url' => $uploadUrl]);
            $response = Http::withHeaders([
                'Content-Type' => $mimeType,
            ])->put($uploadUrl['url'], file_get_contents($document_back));

            return get_success_response(['message' => 'File uploaded successfully'], $response->status());
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 500);
        }
    }

    public function getPriceRate($payload)
    {
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
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        $ratePayload = [
            "coin" => $request->coin,
            "operation" => "BUY",
            "userId" => $customer->manteca_user_id
        ];

        $codeResponse = $this->getPriceRate($ratePayload);

        if (!isset($codeResponse['code'])) {
            return get_error_response(['error' => 'Unable to retrieve rate code']);
        }

        $payload = [
            "userId" => $customer->manteca_user_id,
            "amount" => $request->amount,
            "coin" => $request->coin,
            "operation" => "BUY",
            "code" => $codeResponse['code']
        ];

        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'order', $payload);

        if ($response->ok()) {
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
            'userId' => $customer->manteca_user_id,
            'coin' => $request->coin,
            'cbu' => $request->cbu,
            'amount' => $request->amount
        ];

        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'fiat/withdraw', $payload);

        if ($response->ok()) {
            return get_success_response($response->json());
        }

        Log::debug("Error creating Manteca payout: ", ['response' => $response]);
        return get_error_response(['error' => 'Unable to process withdrawal']);
    }
}
