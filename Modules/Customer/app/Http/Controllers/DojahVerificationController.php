<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Controller;
use Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Log;
use Modules\Customer\app\Models\DojahVerification;
use Modules\Customer\App\Services\DojahServices;

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
            'customer_id' => 'required|string',
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
        ]);

        if ($validate->fails()) {
            return get_error_response(['errors' => $validate->errors()], 422);
        }

        try {
            $validatedData = $validate->validate();
            $validatedData['input_type'] = "base64";
            $validatedData['image'] = $request->selfieimage;
            $validatedData['dob'] = $validatedData['date_of_birth'] = $request->dob ?? $request->date_of_birth;

            // Call the Bridge API
            $bridgePayload = [
                "type" => "individual",
                "first_name" => $request->first_name,
                "middle_name" => $request->middle_name,
                "last_name" => $request->last_name,
                "email" => $request->email,
                "phone" => $request->phone,
                "birth_date" => $request->dob,
                "address" => [
                    "street_line_1" => $request->street,
                    "street_line_2" => $request->landmark,
                    "city" => $request->lga,
                    "state" => $request->state,
                    "postal_code" => $request->input('postal_code', ''),
                    "country" => $request->input('country', ''),
                ],
                "gov_id_image_front" => $this->formatBase64Image($request->imageFrontSide, 'jpeg'),
                "gov_id_image_back" => $this->formatBase64Image($request->imageBackSide, 'jpeg'),
                "proof_of_address_document" => $this->formatBase64Image($request->proof_of_address_document, 'jpeg'),
                "tax_identification_number" => $request->tax_identification_number,
                "endorsements" => ["sepa"]
            ];


            $bridge = new BridgeController();
            $bridgeData = $bridge->sendRequest("/v0/customers", 'POST', $bridgePayload);

            if (isset($bridgeData['code']) && $bridgeData['code'] == 'invalid_parameters') {
                return get_error_response($bridgeData);
            }

            Log::info("Bridge Data: ", $bridgeData);

            return get_success_response($bridgeData);
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

    private function formatBase64Image($imageUrl, $format = 'jpeg')
    {
        // Ensure the URL is valid
        if (empty($imageUrl)) {
            return null;
        }

        // Extract the base64 content (assumes the input URL is already base64 encoded)
        $base64Data = file_get_contents($imageUrl); // Fetch the image content from the URL
        $encodedData = base64_encode($base64Data); // Encode the binary data into base64

        // Format as a proper data URI
        return "data:image/{$format};base64,{$encodedData}";
    }

}
