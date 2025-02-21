<?php

namespace App\Http\Controllers;

use App\Mail\MagicLinkEmail;
use App\Models\Balance;
use App\Models\User;
use Http;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\Currencies\app\Models\Currency;
use Tymon\JWTAuth\Facades\JWTAuth;
use Jijunair\LaravelReferral\Models\Referral;

class MagicLinkController extends Controller
{
    public $crypto_base_url;
    public function __construct()
    {
        $this->crypto_base_url = env('CRYPTO_BASE_URL', "https://crypto.yativo.com/api/authentication");
    }
    /**
     * Send magic link to user email.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMagicLink(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email',
                'base_url' => 'sometimes|url',
            ]);

            $success = [];
            $success['message'] = 'We have sent you a One time login O.T.P, Please check your email.';

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                $user = new User();
                $user->email = $request->email;
            } else {
                $success['is_registered'] = true;
            }

            $token = rand(10111, 99999);
            $user->login_token = $token;
            $user->login_token_created_at = now();
            $user->save();

            $magicLink = $request->base_url . '?magic=' . $token;
            Mail::to($user)->send(new MagicLinkEmail($magicLink, $token));

            return get_success_response($success);

        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Log the user in using a magic login link token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginWithMagicLink(Request $request)
    {
        try {
            $token = $request->token;

            $user = User::where('email', $request->email)
                ->where('login_token', $token)
                ->where('login_token_created_at', '>=', now()->subMinutes(500))
                ->first();

            if (!$user) {
                return get_error_response(['error' => 'The One time login O.T.P, is invalid or has expired.']);
            }

            Auth::login($user);

            $user->login_token = null;
            $user->last_login_at = now();
            $user->login_token_created_at = null;
            $user->save();

            $token = auth()->login($user);

            if ($token === false) {
                return get_error_response(['error' => 'Unauthorized'], 401);
            }

            $success = $this->respondWithToken($token);

            // check if user has been registered for over 24 hours
            if ($user->has_crypto_login == false) {
                $payload = [
                    'username' => explode('@', $user->email)[0],
                    'email' => $user->email,
                    'user_password' => $user->email,
                ];

                $response = Http::post("{$this->crypto_base_url}/registration", $payload);
                $result = $response->json();
                if (isset($result['success']) && $result['success'] === true) {
                    $user->has_crypto_login = true;
                    $user->save();
                }
            } else {
                // check if user has been registered for over 24 hours
                $payload = [
                    'email' => $user->email,
                    'user_password' => $user->email,
                ];
                $response = Http::post("{$this->crypto_base_url}/login", $payload);
                $result = $response->json();
                if (isset($result['success']) && $result['success'] === true) {
                    $user->has_crypto_login = true;
                    $user->save();
                }
            }

            // $success = json_decode($success, true);
            $result = array_merge($user->toArray(), $success, ['crypto_api' => $result]);

            return get_success_response($result);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Complete user registration using a magic login link token.
     *
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeRegistration(Request $request, $token)
    {
        try {
            // Validate user input
            $validate = Validator::make($request->all(), [
                'name' => 'required',
                'businessName' => 'required',
                'idNumber' => 'nullable|string',
                'idType' => 'nullable|string',
                'firstName' => 'nullable|string',
                'lastName' => 'nullable|string',
                'phoneNumber' => 'nullable|string',
                'city' => 'nullable|string',
                'state' => 'nullable|string',
                'country' => 'nullable|string',
                'zipCode' => 'nullable|string',
                'street' => 'nullable|string',
                'additionalInfo' => 'nullable|string',
                'houseNumber' => 'nullable|string',
                'verificationDocument' => 'nullable|string',
                'email' => 'required|email',
                'password' => 'required|min:6',
                'account_type' => 'required|in:individual,company'
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            // Find user with matching token
            $user = User::where('login_token', $token)
                ->where('email', $request->email)
                ->where('login_token_created_at', '>=', now()->subMinutes(5))
                ->first();

            // Check if user was found
            if (!$user) {
                return get_error_response(['error' => 'The one time login token is invalid or has expired.']);
            }

            $validator['membership_id'] = ($request->is_business == true) ? uuid(9, "B") : uuid(9, 'P');

            // Update user with validated data
            $validator['password'] = bcrypt(generate_uuid());
            $validator['raw_data'] = $request->all();
            $validator['registration_country'] = get_iso3_by_iso2($request->ipinfo->country);
            $userData = $user->update($validator);

            // implement referal system
            if ($request->has('referred_by')) {
                $referral = Referral::create([
                    'user_id' => $user->id,
                    'referred_by' => $request->referred_by,
                ]);

                $referrer = Referral::userByReferralCode($request->referred_by);
                if ($referrer) {
                    $wallet = $user->getWallet('bonus');
                    if ($wallet) {
                        $wallet->deposit(get_settings_value('referal_commission', 0), 'bonus', $request->account_type, ["description" => "Referral Bonus"], ["description" => "Referral Bonus"]);
                    }
                }
            }
            // Return success response
            return get_success_response($user, 201);

        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Respond with access token.
     *
     * @param  string  $token
     * @return array // \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ];
    }

    public function cryptoAPI(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'crypto_token' => 'required',
        ]);

        if ($validator->fails()) {
            return get_error_response(['error' => $validator->errors()->toArray()]);
        }

        $payload = [
            'email' => auth()->user()->email,
            'user_password' => $request->crypto_token
        ];

        $response = Http::post("{$this->crypto_base_url}/login", $payload);
        if ($response->json()['status'] === true) {
            return get_success_response("Please verify OTP sent to email");
        } else {
            return get_error_response(['error' => $response->json()['message']]);
        }
    }

    public function getCryptoToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'crypto_otp' => 'required',
        ]);

        
        if ($validator->fails()) {
            return get_error_response(['error' => $validator->errors()->toArray()]);
        }

        $payload = [
            'email' => auth()->user()->email,
            'otp' => $request->crypto_otp
        ];

        $response = Http::post("{$this->crypto_base_url}/otp_verification", $payload);
        if ($response->json()['status'] === true) {
            return get_success_response(["crypto_api_key" => $response->json()['result']['apiKey']]);
        } else {
            return get_error_response(['error' => $response->json()['message']]);
        }
    }
}
