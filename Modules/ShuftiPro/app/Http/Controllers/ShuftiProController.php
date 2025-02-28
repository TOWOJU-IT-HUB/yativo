<?php

namespace Modules\ShuftiPro\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\BusinessVerificationEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\ShuftiPro\app\Models\ShuftiPro;
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function webhook(Request $request)
    {
        try {
            $shufti = new ShuftiProServices();
            $process = $shufti->callback(request());
            return $process;
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function verifyBusiness($request)
    {
        try {
            $shufti = new ShuftiProServices();

            $businessName = $request['business_name'];
            $businessRegistrationNumber = $request['registration_number'];
            $businessJurisdictionCode = $request['business_jurisdiction_code'];
            $businessCountry = $request['business_country'];;


            $response = $shufti->businessVerification($businessName, $businessRegistrationNumber, $businessJurisdictionCode, $businessCountry);
            
            return get_success_response(['message' => 'Business verification request received and will be processed within the next 48hours. Thank you.'], 200);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function callback(Request $request)
    {
        try {
            $shuftpro = new ShuftiPro();
        } catch (\Throwable $th) {
            //throw $th;
        }
        
        return redirect()->away(request()->redirect_url ?? getenv('WEB_URL'));
    }

    public function business_callback(Request $request)
    {
        try {
            $shuftpro = new ShuftiPro();
        } catch (\Throwable $th) {
            //throw $th;
        }
        
        return redirect()->away(request()->redirect_url ?? getenv('WEB_URL'));
    }
}
