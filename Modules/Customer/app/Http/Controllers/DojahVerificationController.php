<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TransFiController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\DojahVerification;
use Modules\Customer\App\Services\DojahServices;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;
use Towoju5\Bitnob\Bitnob;

class DojahVerificationController extends Controller
{
    public function customerVerification(Request $request)
    {
        $rules = [
            'customer_id' => 'required|exists:customers,customer_id',
            'type' => 'required|in:individual,business',
            'email' => 'required|email',
            'address.street_line_1' => 'required|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.subdivision' => 'nullable|string|max:3',
            'address.postal_code' => 'required|string|max:20',
            'address.country' => 'required|string|size:3',
            // 'signed_agreement_id' => 'required|uuid',
        ];

        if ($request->input('type') === 'individual') {
            $rules = array_merge($rules, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'birth_date' => 'required|date|before:today',
                'employment_status' => 'required|string|in:employed,self_employed,unemployed',
                'expected_monthly_payments' => 'required|string|in:5000_9999,10000_14999,15000_plus',
                'acting_as_intermediary' => 'required|boolean',
                'most_recent_occupation' => 'required|string',
                'account_purpose' => 'required|string|in:purchase_goods_and_services,other',
                'source_of_funds' => 'required|string',
                'identifying_information' => 'required|array|min:1',
                'identifying_information.*.type' => 'required|string|in:ssn,drivers_license,passport',
                'identifying_information.*.issuing_country' => 'required|string|size:3',
                'identifying_information.*.number' => 'required|string',
                'documents' => 'required|array|min:1',
                'documents.*.purposes' => 'required|array|min:1',
                'documents.*.file' => 'required|string|starts_with:data:image',
            ]);
        } elseif ($request->input('type') === 'business') {
            $rules = array_merge($rules, [
                'registered_address.street_line_1' => 'required|string|max:255',
                'registered_address.city' => 'required|string|max:255',
                'registered_address.subdivision' => 'nullable|string|max:255',
                'registered_address.postal_code' => 'required|string|max:20',
                'registered_address.country' => 'required|string|size:3',
                'business_type' => 'required|string',
                'business_industry' => 'required|string',
                'business_legal_name' => 'required|string|max:255',
                'estimated_annual_revenue_usd' => 'required|string',
                'expected_monthly_payments_usd' => 'required|numeric',
                'ultimate_beneficial_owners' => 'required|array|min:1',
                'ultimate_beneficial_owners.*.first_name' => 'required|string|max:255',
                'ultimate_beneficial_owners.*.last_name' => 'required|string|max:255',
                'ultimate_beneficial_owners.*.birth_date' => 'required|date|before:today',
                'ultimate_beneficial_owners.*.email' => 'required|email',
                'ultimate_beneficial_owners.*.address.street_line_1' => 'required|string|max:255',
                'ultimate_beneficial_owners.*.address.country' => 'required|string|size:3',
                'ultimate_beneficial_owners.*.has_ownership' => 'required|boolean',
                'ultimate_beneficial_owners.*.ownership_percentage' => 'required_if:ultimate_beneficial_owners.*.has_ownership,true|numeric|min:0|max:100',
                'ultimate_beneficial_owners.*.is_director' => 'required|boolean',
                'ultimate_beneficial_owners.*.is_signer' => 'required|boolean',
                'ultimate_beneficial_owners.*.documents' => 'required|array|min:1',
                'ultimate_beneficial_owners.*.documents.*.purposes' => 'required|array|min:1',
                'ultimate_beneficial_owners.*.documents.*.file' => 'required|string|starts_with:data:image',
            ]);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $validatedData['signed_agreement_id'] = $this->generateSignedAgreementId();

        try {
            $bridge = new BridgeController();
            $bridgeData = $bridge->addCustomerV1($validatedData);

            if (isset($bridgeData['code']) && $bridgeData['code'] === 'invalid_parameters') {
                return get_error_response(['error' => $bridgeData]);
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
                'idImage' => $validatedData['documents'][0]['file'] ?? null,
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

            // Queue webhook notification
            dispatch(function () use ($userId, $bridgeData) {
                $webhook = Webhook::whereUserId($userId)->first();
                if ($webhook) {
                    WebhookCall::create()
                        ->meta(['_uid' => $webhook->user_id])
                        ->url($webhook->url)
                        ->useSecret($webhook->secret)
                        ->payload([
                            "event.type" => "customer.created",
                            "payload" => $bridgeData,
                        ])
                        ->dispatchSync();
                }
            })->afterResponse();
            return get_success_response($bridgeData);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }
    }

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


    public function KycWebhook(Request $request)
    {
        try {
            if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' && array_key_exists('x-dojah-signature', $_SERVER)) {
                //get the request body
                $input = @file_get_contents("php://input");

                define('DOJAH_SECRET_KEY', getenv("DOJA_PRIVATE_KEY"));
                //validate request
                if ($_SERVER['HTTP_X_DOJAH_SIGNATURE'] === hash_hmac('sha256', $input, DOJAH_SECRET_KEY)) {
                    http_response_code(200);

                    //parse event
                    $event = json_decode($input, true);

                    if (!isset($event['referenceId'])) {
                        Log::formatMessage($request->all());
                    }
                    unset($event['verificationUrl']);
                    $db_event = $event;

                    // since reference ID is set locate the verification type and update record

                    // check if it's customer
                    //send webhook with status
                }
            }
            exit();
        } catch (\Throwable $th) {
            Log::formatMessage($request->all());
        }
    }

    public function kycStatus(Request $request)
    {
        try {
            //code...
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

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
