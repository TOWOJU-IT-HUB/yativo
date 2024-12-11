<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\DojahVerification;
use Modules\Customer\App\Services\DojahServices;

class ExDojahVerificationController extends Controller
{
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
            'selfieimage' => 'required|string',  // base64_encrypted images.
            'photoidimage' => 'required|string', // base64_encrypted images.
            'imageFrontSide' => 'required|string',  // base64_encrypted images.
            'imageBackSide' => 'sometimes|string', // base64_encrypted images.
        ]);


        if ($validate->fails()) {
            return get_error_response(['errors' => $validate->errors()], 422);
        }

        $status = "success";

        $validatedData = $validate->validate();
        $validatedData['input_type'] = "base64";
        $validatedData['image'] = $request->selfieimage;
        $validatedData['dob'] = $validatedData['date_of_birth'] = $request->dob ?? $request->date_of_birth;

        $dojah = new DojahServices();
        $result = (array) $dojah->verifyCustomer($validatedData);

        if (isset($result['document_analysis']['entity']['status']['overall_status']) && $result['document_analysis']['entity']['status']['overall_status'] < 1) {
            $document_analysis = "failed";
        }
        if (isset($result['selfie_verification']['entity']['selfie']['confidence_value']) and $result['selfie_verification']['entity']['selfie']['confidence_value'] < 70) {
            $selfie_verification = "failed";
        }

        if (isset($result['liveness_check']['entity']['liveness']['confidence']) and $result['liveness_check']['entity']['liveness']['confidence'] < 70) {
            $liveness_check = "failed";
        }

        if (isset($result['address_verification']['entity']['status']) and $result['address_verification']['entity']['status'] == "failed") {
            $address_verification = "failed";
        }

        if ($document_analysis = "failed" or $selfie_verification = "failed" or $liveness_check = "failed" or $address_verification = "failed") {
            $status = "failed";
        }

        if (isset($result['address_verification']['entity']['status']) && $result['address_verification']['entity']['status'] == "pending") {
            $status = "pending";
        }

        $verify = DojahVerification::create([
            "user_request" => $validatedData,
            "kyc_status" => $status,
            "dojah_response" => $result,
            "verification_response" => $result
        ]);

        if ($status == "failed") {
            return get_error_response($result);
        }
        return get_success_response($result);
    }

    public function verifyBusiness(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'international_number' => 'required|string',
            'country_code' => 'required|string',
            'business_name' => 'required|string', // business name
            'business_mobile' => 'required|string',
            'first_name' => 'required|string',
            'middle_name' => 'required|string',
            'date_of_birth' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string',
            'mobile' => 'required|string',
            'street' => 'required|string',
            'landmark' => 'required|string',
            'lga' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'ip_address' => 'required|string|ip',
        ]);


        if ($validate->fails()) {
            return get_error_response(['errors' => $validate->errors()], 422);
        }

        $validatedData = $validate->validate();

        $validatedData['entity_name'] = $request->business_name;
        $validatedData['phone'] = $request->mobile;


        $dojah = new DojahServices();

        $dojah->verifyBusiness($validatedData);

        return get_success_response([
            'business_verfication' => 'pending',
            "message" => "Please wait while we manually verify your data"
        ]);
    }

    // public function businessSerach(Request $request)
    // {
    //     $validate = Validator::make($request->all(), [
    //         'customer_id' => 'required|string',
    //         'company' => 'required|string',
    //         'country_code ' => 'required|string'
    //     ]);

    //     if ($validate->fails()) {
    //         return get_error_response(['errors' => $validate->errors()], 422);
    //     }

    //     $endpoint = 'kyb/business/search';
    //     $validatedData = $validate->validated();
    //     $response = DojahServices::handle($endpoint, $validatedData);

    //     return ($response);    
    // }

    // public function businessDetails(Request $request)
    // {
    //     $validate = Validator::make($request->all(), [
    //         'customer_id' => 'required|string',
    //         'international_number' => 'required|string',
    //         'country_code' => 'required|string',
    //     ]);

    //     if ($validate->fails()) {
    //         return get_error_response(['errors' => $validate->errors()], 422);
    //     }

    //     $endpoint = 'kyb/business/detail';
    //     $validatedData = $validate->validated();
    //     $response = DojahServices::handle($endpoint, $validatedData);

    //     return ($response);    
    // }

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
                        \Log::formatMessage($request->all());
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
            \Log::formatMessage($request->all());
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
