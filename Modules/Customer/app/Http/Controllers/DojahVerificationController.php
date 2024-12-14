<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Controller;
use Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Log, DB;
use Modules\Customer\app\Models\DojahVerification;
use Modules\Customer\App\Services\DojahServices;
use Towoju5\Bitnob\Bitnob;

class DojahVerificationController extends Controller
{
    // public function customerVerification(Request $request)
    // {
    //     $validate = Validator::make($request->all(), [
    //         'customer_id' => 'required|string',
    //         'first_name' => 'required|string',
    //         'last_name' => 'required|string',
    //         'middle_name' => 'required|string',
    //         'email' => 'required|email',
    //         'phone' => 'required|string',
    //         'dob' => 'required|string',
    //         'gender' => 'required|string',
    //         'mobile' => 'required|string',
    //         'street' => 'required|string',
    //         'landmark' => 'required|string',
    //         'lga' => 'required|string',
    //         'state' => 'required|string',
    //         "longitude" => "required",
    //         "latitude" => "required",
    //         'ip_address' => 'required|string|ip',
    //         'selfieimage' => 'required|url',
    //         'photoidimage' => 'required|url',
    //         'imageFrontSide' => 'required|url',
    //         'imageBackSide' => 'sometimes|url',
    //     ]);


    //     if ($validate->fails()) {
    //         return get_error_response(['errors' => $validate->errors()], 422);
    //     }

    //     $status = "success";

    //     $validatedData = $validate->validate();
    //     $validatedData['input_type'] = "base64";
    //     $validatedData['image'] = $request->selfieimage;
    //     $validatedData['dob'] = $validatedData['date_of_birth'] = $request->dob ?? $request->date_of_birth;

    //     $dojah = new DojahServices();
    //     $result = (array) $dojah->verifyCustomer($validatedData);

    //     if (isset($result['document_analysis']['entity']['status']['overall_status']) && $result['document_analysis']['entity']['status']['overall_status'] < 1) {
    //         $document_analysis = "failed";
    //     }
    //     if (isset($result['selfie_verification']['entity']['selfie']['confidence_value']) and $result['selfie_verification']['entity']['selfie']['confidence_value'] < 70) {
    //         $selfie_verification = "failed";
    //     }

    //     if (isset($result['liveness_check']['entity']['liveness']['confidence']) and $result['liveness_check']['entity']['liveness']['confidence'] < 70) {
    //         $liveness_check = "failed";
    //     }

    //     if (isset($result['address_verification']['entity']['status']) and $result['address_verification']['entity']['status'] == "failed") {
    //         $address_verification = "failed";
    //     }

    //     if ($document_analysis = "failed" or $selfie_verification = "failed" or $liveness_check = "failed" or $address_verification = "failed") {
    //         $status = "failed";
    //     }

    //     if (isset($result['address_verification']['entity']['status']) && $result['address_verification']['entity']['status'] == "pending") {
    //         $status = "pending";
    //     }

    //     $verify = DojahVerification::create([
    //         "user_request" => $validatedData,
    //         "kyc_status" => $status,
    //         "dojah_response" => $result,
    //         "verification_response" => $result
    //     ]);

    //     if ($status == "failed") {
    //         return get_error_response($result);
    //     }
    //     return get_success_response($result);
    // }

    public function customerVerification(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'customer_id' => 'required|string|exists:customers,customer_id',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'middle_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'dob' => 'required|string',
            'gender' => 'required|string',
            'mobile' => 'required|string',
            'street' => 'required|string',
            'landmark' => 'required|string',
            'lga' => 'required|string',
            'state' => 'required|string',
            "longitude" => "required",
            "latitude" => "required",
            'ip_address' => 'required|string|ip',
            'selfieimage' => 'required|url',
            'photoidimage' => 'required|url',
            'imageFrontSide' => 'required|url',
            'imageBackSide' => 'sometimes|url',
            'proof_of_address_document' => 'sometimes|url',
            'employment_status' => 'required|string',
            'expected_monthly_payments' => 'required|string',
            'most_recent_occupation' => 'required|string',
            'primary_purpose' => 'required|string',
            'primary_purpose_other' => 'required|string',
            'source_of_funds' => 'required|string',
            'gov_id_country' => 'required|string',
            'postal_code' => 'required',
            'idNumber' => 'required',
            'tax_identification_number' => 'required'
        ]);

        if ($validate->fails()) {
            return get_error_response(['errors' => $validate->errors()], 422);
        }

        try {
            $validatedData = $validate->validate();
            $validatedData['input_type'] = "base64";
            $validatedData['image'] = $request->selfieimage;
            $validatedData['dob'] = $validatedData['date_of_birth'] = $request->dob ?? $request->date_of_birth;

            $bridge = new BridgeController();
            $bridgeData = $bridge->addCustomerV1($validatedData);

            Log::info("Bridge Data: ", $bridgeData);

            /**
             * @method Register the customer Bitnob
             */
            $bitnob = new Bitnob();
            $bitnobPayload = [
                'customerEmail' => $validatedData['email'],
                'idNumber' => $validatedData['idNumber'],
                'idType' => $validatedData['idType'],
                'firstName' => $validatedData['first_name'],
                'lastName' => $validatedData['last_name'],
                'phoneNumber' => $validatedData['phone'],
                'city' => $validatedData['city'],
                'state' => $validatedData['state'],
                'country' => $validatedData['gov_id_country'],
                'zipCode' => $validatedData['zipCode'],
                'line1' => $validatedData['line1'],
                'houseNumber' => $validatedData['houseNumber'],
                'idImage' => $validatedData['idImage'],
                'dateOfBirth' => $validatedData['dob'],
            ];
            
            $bitnob_card_register_user = $bitnob->cards()->regUser($bitnobPayload);

            Log::info("Bitnob Data: ", $bitnob_card_register_user);

            if (isset($bridgeData['code']) && $bridgeData['code'] == 'invalid_parameters') {
                return get_error_response($bridgeData);
            }
            return get_success_response(["customer_verification" => $bridgeData, "virtual_card_activation" => $bitnob_card_register_user]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }
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

}
