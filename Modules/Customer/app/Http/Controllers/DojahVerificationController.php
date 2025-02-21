<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MiscController;
use App\Http\Controllers\TransFiController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\Customer;
use Modules\Customer\app\Models\DojahVerification;
use Modules\Customer\App\Services\DojahServices;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;
use Towoju5\Bitnob\Bitnob;

/* The `DojahVerificationController` class in PHP handles customer verification, KYC webhook
    processing, KYC status requests, and occupation code retrieval with error handling and webhook
    notifications.
 */

class DojahVerificationController extends Controller
{
    /**
     * The function `customerVerification` in PHP validates customer data, generates a signed agreement
     * ID, interacts with external APIs for customer registration, and triggers a webhook notification
     * upon successful customer creation.
     * 
     * @param Request request The `customerVerification` function is a PHP method that handles the
     * verification of customer information based on the provided request data. It performs validation
     * on the request data according to specified rules for individual and business customers. If the
     * validation fails, it returns a JSON response with validation errors. If the validation passes,
     * 
     * @return The `customerVerification` function returns a JSON response. If the validation fails, it
     * returns validation errors with a status code of 422. If there are any errors during the process,
     * it returns an error response with the corresponding message and status code 500. If the process
     * is successful, it returns a success response with the data obtained from the `BridgeController`
     * and a status code of 200
     */
    public function customerVerification(Request $request)
    {
        $rules = [
            'customer_id' => 'required|exists:customers,customer_id',
            'type' => 'required|in:individual,business',
            'email' => 'required|email',
            'address' => 'required|array',
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.purposes' => ['required', 'array'],
            'documents.*.purposes.*' => ['string'], 
            'documents.*.file' => ['required', 'string'],
            'documents.*.description' => ['required', 'string'],
        ];
    
        if ($request->input('type') === 'individual') {
            $rules = array_merge($rules, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'birth_date' => 'required|date|before:today',
                'employment_status' => 'required|string|in:employed,self_employed,unemployed',
                'expected_monthly_payments' => 'required|string|in:0_4999,5000_9999,10000_14999,15000_plus',
                'acting_as_intermediary' => 'required|boolean',
                'most_recent_occupation' => 'required|string',
                'account_purpose' => 'required|string|in:purchase_goods_and_services,other',
                'source_of_funds' => 'required|string',
                'identifying_information' => 'required|array|min:1',
                'documents' => 'sometimes|array',
            ]);
        } elseif ($request->input('type') === 'business') {
            $rules = array_merge($rules, [
                'business_name' => 'required|string|max:255',
                'business_type' => 'required|string|in:sole_proprietorship,partnership,corporation',
                'registration_number' => 'required|string|max:255',
                'tax_identification_number' => 'nullable|string|max:255',
                'contact_person' => 'required|string|max:255',
                'business_address' => 'required|array',
                'business_documents' => 'sometimes|array',
            ]);
        }
    
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            return get_error_response(['errors' => $validator->errors()], 422);
        }
    
        $validatedData = $request->all();
        $validatedData['signed_agreement_id'] = $this->generateSignedAgreementId();
        $validatedData['residential_address'] = $request->address;
        $validatedData['expected_monthly_payments_usd'] = $request->expected_monthly_payments;
    
        try {
            unset($validatedData['customer_id']);
            // return $validatedData;
            $bridge = new BridgeController();
            $bridgeData = $bridge->addCustomerV1($validatedData);
    
            if (isset($bridgeData['code']) && $bridgeData['code'] == 'invalid_parameters') {
                return get_error_response(['error' => $bridgeData['source']]);
            }
    
            $bitnobPayload = [
                'customerEmail' => $validatedData['email'],
                'idNumber' => $validatedData['identifying_information'][0]['number'] ?? null,
                'idType' => $validatedData['identifying_information'][0]['type'] ?? null,
                'firstName' => $validatedData['first_name'] ?? null,
                'lastName' => $validatedData['last_name'] ?? null,
                'phoneNumber' => $request->input('phone', null),
                'city' => $validatedData['address']['city'] ?? null,
                'state' => $validatedData['address']['subdivision'] ?? null,
                'country' => $validatedData['address']['country'] ?? null,
                'zipCode' => $validatedData['address']['postal_code'] ?? null,
                'line1' => $validatedData['address']['street_line_1'] ?? null,
                'idImage' => isset($validatedData['documents'][0]['file'])
                    ? MiscController::uploadBase64ImageToCloudflare($validatedData['documents'][0]['file'])
                    : null,
                'dateOfBirth' => $validatedData['birth_date'] ?? null,
            ];
    
            $bitnob = new Bitnob();
            $bitnobResponse = $bitnob->cards()->regUser($bitnobPayload);
    
            if (isset($bitnobResponse['error'])) {
                return get_error_response(['error' => $bitnobResponse['message'] ?? 'Bitnob registration failed']);
            }
    
            $tranfi = new TransFiController();
            $tranfi->kycForm($request);
            $userId = auth()->id();
            return get_success_response($bridgeData);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()], 500);
        }
    }
    
    /**
     * The function generates a signed agreement ID by making a POST request to a specific URL with certain
     * parameters.
     * 
     * @return The function `generateSignedAgreementId` is making a POST request to
     * https://monorail.onrender.com/dashboard/generate_signed_agreement_id with specific parameters. It
     * then retrieves the response and returns the value of the key 'signed_agreement_id' from the JSON
     * response.
     */
    private function generateSignedAgreementId()
    {
        $curl = Http::post("https://monorail.onrender.com/dashboard/generate_signed_agreement_id", [
            'customer_id' => NULL,
            'email' => NULL,
            'token' => NULL,
            'type' => 'tos',
            'version' => 'v5',
        ]);
        $response = $curl->json();
        return $response['signed_agreement_id'];
    }


    /**
     * The PHP function `KycWebhook` processes a KYC webhook request, updates customer KYC status, and
     * sends a notification to a webhook URL if conditions are met.
     * 
     * @param Request request The code snippet you provided is a PHP function that handles a KYC (Know Your
     * Customer) webhook request. Let me explain the key points of the code:
     * 
     * @return The function `KycWebhook` returns a JSON response based on the conditions and processing
     * within the function. Here are the possible return scenarios:
     */
    public function KycWebhook(Request $request)
    {
        try {
            // Check if the request method is POST and the webhook signature is provided
            if ($request->isMethod('post') && $request->hasHeader('x-webhook-signature') && ($request->event_type == 'customer.created')) {
                // Get the request body
                $input = $request->getContent();

                // Decode the JSON payload to an associative array
                $data = json_decode($input, true);

                $customer = Customer::where('email', $data['event_object']['email'])->first();
                if (!$customer) {
                    return response()->json(['message' => 'Customer not found'], 404);
                }

                if ($data['event_type'] != 'customer.created') {
                    return response()->json(['message' => 'ok'], 200);
                }

                // Extract the important KYC data
                $kycData = [
                    'first_name' => $data['event_object']['first_name'],
                    'last_name' => $data['event_object']['last_name'],
                    'email' => $data['event_object']['email'],
                    'status' => $data['event_object']['status'],
                    'customer_id' => $customer->customer_id,
                ];

                // Log the KYC data for debugging
                Log::info('KYC Webhook Received:', $kycData);

                // Process the KYC data
                $customer->update([
                    'customer_kyc_status' => $data['event_object']['status']
                ]);

                // Send a success response
                $userId = $customer->user_id;

                // Queue webhook notification
                dispatch(function () use ($userId, $kycData) {
                    $webhook = Webhook::whereUserId($userId)->first();
                    if ($webhook) {
                        WebhookCall::create()
                            ->meta(['_uid' => $webhook->user_id])
                            ->url($webhook->url)
                            ->useSecret($webhook->secret)
                            ->payload([
                                "event.type" => "customer.created",
                                "payload" => $kycData,
                            ])
                            ->dispatchSync();
                    }
                })->afterResponse();
                return response()->json(['message' => 'Webhook received successfully'], 200);
            } else {
                return response()->json(['error' => 'Invalid request method or missing signature'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error processing KYC Webhook: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * The function kycStatus handles the KYC status request in PHP, with error handling using a try-catch
     * block.
     * 
     * @param Request request The `` parameter in the `kycStatus` function is an instance of the
     * `Request` class. It is typically used in Laravel applications to handle incoming HTTP requests and
     * retrieve data from those requests. You can access request parameters, headers, and other information
     * using this object.
     */
    public function kycStatus(Request $request)
    {
        try {
            //code...
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * This PHP function retrieves occupation codes from an API and returns a success response with the
     * data or an error response with the message if an exception occurs.
     * 
     * @param Request request The `occupationCodes` function is making a HTTP GET request to
     * '//api.bridge.xyz/v0/lists/occupation_codes' to retrieve a list of occupation codes. If the
     * request is successful, it returns a success response with the retrieved data. If an error occurs
     * during the request, it catches the exception
     * 
     * @return The `occupationCodes` function is making a HTTP GET request to
     * '//api.bridge.xyz/v0/lists/occupation_codes' to retrieve a list of occupation codes. If the
     * request is successful, it returns a success response with the retrieved data. If an error occurs
     * during the request, it returns an error response with the error message.
     */
    public function occupationCodes(Request $request)
    {
        try {
            $dojah = Http::get('//api.bridge.xyz/v0/lists/occupation_codes');
            $response = $dojah->json();
            return get_success_response($response);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }
    }
}
