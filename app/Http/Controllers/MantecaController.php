<?php

namespace App\Http\Controllers;

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
            'docType' => 'required|string',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf',
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();
        $document = $request->file('file');
        $filename = $document->getClientOriginalName();
        $mimeType = $document->getMimeType();

        try {
            $uploadUrl = $this->getUploadUrl($customer->manteca_user_id, $request->docType, $filename);
            if (!$uploadUrl) {
                return get_error_response(['error' => 'Upload URL not received'], 500);
            }

            $response = Http::withHeaders([
                'Content-Type' => $mimeType,
            ])->put($uploadUrl, file_get_contents($document));

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

        return get_error_response(['error' => 'Unable to process withdrawal']);
    }
}
