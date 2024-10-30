<?php

namespace Modules\ShuftiPro\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\BusinessVerificationEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\ShuftiPro\app\Services\ShuftiProServices;

class ShuftiProController extends Controller
{
    public function shuftiPro(Request $request)
    {
        try {
            $shufti = new ShuftiProServices();
            $process = $shufti->init($request);
            return $process;
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function webhook(Request $request)
    {
        try {
            $shufti = new ShuftiProServices();
            $process = $shufti->callback(request());
            return $process;
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function business(Request $request)
    {
        try {
            $shufti = new ShuftiProServices();
            $process = $shufti->init(request());

            // mail the verification link to the business email
            $sendEmail = Mail::to($request->email)->send(new BusinessVerificationEmail($request->name, $request->email, $process->data->verification_url));
            if ($sendEmail) {
                return get_success_response(['message' => 'Email sent successfully']);
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function verifyBusiness(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'name' => 'required|string',
                'email' => 'required|string',
                'business_name' => 'required|string',
                'business_country' => 'required|string|size:2',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()], 400);
            }

            $shufti = new ShuftiProServices();

            $businessName = $request->input('business_name');
            $businessRegistrationNumber = $request->input('registration_number');
            $businessJurisdictionCode = $request->input('business_jurisdiction_code');
            $businessCountry = $request->input('business_country');;


            $response = $shufti->businessVerification($businessName, $businessRegistrationNumber, $businessJurisdictionCode, $businessCountry);
            
            return get_success_response(['message' => 'Business verification request received and will be processed within the next 48hours. Thank you.'], 200);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function generateShuftiProToken()
    {
        try {
            $client_id = getenv('SHUFTI_PRO_CLIENT_ID');
            $secret_key = getenv('SHUFTI_PRO_SECRET_KEY');
            $token = base64_encode("$client_id:$secret_key");
            return get_success_response($token);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function callback(Request $request)
    {
        try {
        } catch (\Throwable $th) {
            //throw $th;
        }
        
        return redirect()->away(getenv('WEB_URL'));
    }
}
