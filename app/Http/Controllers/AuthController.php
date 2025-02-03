<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Models\Balance;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Socialite\Facades\Socialite;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;
use Modules\Currencies\app\Models\Currency;

class AuthController extends Controller implements UpdatesUserProfileInformation
{
    /**
     * Login user and return token
     * 
     * @param Request $request - incoming request with email and password
     * 
     * 1. Validate email and password
     * 2. Attempt login with credentials 
     * 3. If login fails, return error response
     * 4. If login succeeds, generate and return JWT token
     */
    public function login(Request $request)
    {
        // Validate the incoming request data
        try {
            $validator = Validator::make($request->all(), [
                'account_id' => 'required',
                'app_secret' => 'required',
            ]);

            $email = base64_decode($request->account_id);

            if ($validator->fails()) {
                return get_error_response(['error' => $validator->errors()->toArray()], 422);
            }

            $credentials = ['email' => $email, 'password' => $request->app_secret];

            $token = auth()->attempt($credentials);

            $user = User::where('email', $request->email)->first();

            if ($token === false) {
                return get_error_response(['error' => 'Invalid login credentials'], 401);
            }
            return $this->respondWithToken($token);
        } catch (\Throwable $th) {
            return get_error_response(['error' => "Sorry we're unable to process your request at the moment, please contact support."]);
        }
    }


    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Respond with the access token.
     *
     * @param  string $token
     * @param  array|null $parameters
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $parameters = null)
    {
        $arr = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ];

        if (!empty($parameters)) {
            $arr['is_registered'] = $parameters;
        }

        return get_success_response($arr);
    }


    /**
     * Register a new user.
     *
     * @param Request $request - The incoming request data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            // Validate the incoming request data
            $validate = Validator::make($request->all(), [
                'name' => 'required',
                'bussinessName' => 'required',
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
                'email' => 'required|email|unique:users',
                'password' => 'required|min:6',
            ], [
                'name.required' => 'The name field is mandatory.',
                'bussinessName.required' => 'The business name field is mandatory.',
                'idNumber.string' => 'The ID number must be a string.',
                'idType.string' => 'The ID type must be a string.',
                'firstName.string' => 'The first name must be a string.',
                'lastName.string' => 'The last name must be a string.',
                'phoneNumber.string' => 'The phone number must be a string.',
                'city.string' => 'The city must be a string.',
                'state.string' => 'The state must be a string.',
                'country.string' => 'The country must be a string.',
                'zipCode.string' => 'The zip code must be a string.',
                'street.string' => 'The street must be a string.',
                'additionalInfo.string' => 'The additional info must be a string.',
                'houseNumber.string' => 'The house number must be a string.',
                'verificationDocument.string' => 'The verification document must be a string.',
                'email.required' => 'The email field is mandatory.',
                'email.email' => 'Please enter a valid email address.',
                'email.unique' => 'Only one account per email is supported!',
                'password.required' => 'The password field is mandatory.',
                'password.min' => 'The password must be at least 6 characters long.',
            ]);

            if ($validate->fails()) {
                return get_error_response($validate->errors(), 422);
            }

            // Extract validated data
            $validatedData = $validate->validated();

            // Hash the password
            $validatedData['password'] = bcrypt($request->password);

            // Save the raw request data excluding password
            $validatedData['raw_data'] = json_encode($request->except(['password']));

            // Create the new user
            $user = User::create($validatedData);

            // Create default balances for currencies
            if ($user) {
                try {
                    $currencies = Currency::all();
                    foreach ($currencies as $currency) {
                        Balance::create([
                            "user_id" => $user->id,
                            "currency_name" => $currency->currency_name,
                            "currency_code" => $currency->wallet,
                            "main_balance" => $currency->main_balance,
                            "ledger_balance" => $currency->ledger_balance,
                            "currency_symbol" => $currency->currency_icon,
                        ]);
                    }
                } catch (\Throwable $th) {
                    Log::error(json_encode(['error_creating_balance' => $th->getMessage()]));
                }
            }

            return get_success_response(['status' => 'success', 'data' => $user], 201);
        } catch (\Throwable $th) {
            return get_error_response(['status' => 'error', 'message' => $th->getMessage()], 500);
        }
    }


    /**
     * Send verification OTP to user's email
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function sendVerificationOtp(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return get_error_response(['error' => $validator->errors()->toArray()], 422);
        }

        // Generate a random 6-digit OTP
        $otp = mt_rand(100001, 999999);

        // Save the OTP in the user's record
        $user = User::where('email', $request->input('email'))->first();
        $user->verification_otp = $otp;
        $user->save();

        sendOtpEmail($user->email, $otp);

        return get_success_response(['message' => 'OTP sent successfully'], 200);
    }


    /**
     * Handle forgot password request
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return get_error_response(['error' => $validator->errors()->toArray()], 422);
        }

        // Generate a random 6-digit OTP
        $otp = mt_rand(100000, 999999);

        // Save the OTP in the user's record (you might want to store it securely, depending on your application)
        $user = User::where('email', $request->input('email'))->first();
        $user->verification_otp = $otp;
        $user->save();

        // Send the OTP to the user's email 
        sendOtpEmail($user->email, $otp);

        return get_success_response(['message' => 'OTP sent successfully'], 200);
    }


    /**
     * Reset user password with OTP
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPasswordWithOtp(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric',
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return get_error_response(['error' => $validator->errors()->toArray()], 422);
        }

        // Retrieve the user by email
        $user = User::where('email', $request->input('email'))->first();

        if ($user && $user->verification_otp == $request->input('otp')) {

            // Reset the user's password
            $user->update([
                'password' => bcrypt($request->input('password')),
                'verification_otp' => null
            ]);

            return get_success_response(['message' => 'Password reset successfully'], 200);
        }

        return get_error_response(['error' => 'Invalid OTP'], 422);
    }


    /**
     * Get the authenticated user's profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function profile(Request $request)
    {
        try {
            $profile = $request->user()->toArray();
            $user = User::with('business')->whereId(auth()->id())->first();

            if (!$user) {
                return get_error_response(['error' => 'User not found'], 404);
            }

            $business = Business::whereUserId($user->id)->first();

            // return response()->json($business);
            $errors = [];
            if ($profile['is_business'] == true) {
                $requiredFields = [
                    'business_legal_name',
                    'business_operating_name',
                    'incorporation_country',
                    'business_operation_address',
                    'entity_type',
                    'business_registration_number',
                    'business_tax_id',
                    'business_industry',
                    'business_sub_industry',
                    'business_description',
                    'business_website',
                    'account_purpose',
                    'plan_of_use',
                    'is_pep_owner',
                    'is_ofac_sanctioned',
                    'shareholder_count',
                    'shareholders',
                    'directors_count',
                    'directors',
                    'documents',
                    'administrator',
                    'use_case',
                    'estimated_monthly_transactions',
                    'estimated_monthly_payments',
                    'is_self_use',
                    'terms_agreed_date'
                ];

                foreach ($requiredFields as $field) {
                    if (empty($business[$field])) {
                        $errors[] = $field;
                    }
                }
                $profile['errors'] = $errors;

            } else {
                $profile['errors'] = [
                    'is_business' => false
                ];
            }
            return get_success_response($profile);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }


    public function update(\Illuminate\Foundation\Auth\User $user, array $input = [])
    {
        $user = auth()->user();
        $input = request();

        if ($input->has('is_business') || ($input->has('user_type') && $input->user_type == 'business')) {
            $user->is_business = $input->has('is_business') ? $input->is_business : true;
            $user->user_type = $user->is_business ? 'business' : 'individual';
            $user->save();
        }

        $user->update(array_filter([
            "name" => $input['name'] ?? $user->name,
            "bussinessName" => $input['bussinessName'] ?? $user->bussinessName,
            "firstName" => $input['firstName'] ?? $user->firstName,
            "lastName" => $input['lastName'] ?? $user->lastName,
            "phoneNumber" => $input['phoneNumber'] ?? $user->phoneNumber,
            "city" => $input['city'] ?? $user->city,
            "state" => $input['state'] ?? $user->state,
            "country" => $input['country'] ?? $user->country,
            "zipCode" => $input['zipCode'] ?? $user->zipCode,
            "street" => $input['street'] ?? $user->street,
            "additionalInfo" => $input['additionalInfo'] ?? $user->additionalInfo,
            "houseNumber" => $input['houseNumber'] ?? $user->houseNumber,
            "idNumber" => $input['idNumber'] ?? $user->idNumber,
            "idType" => $input['idType'] ?? $user->idType,
            "idIssuedAt" => $input['idIssuedAt'] ?? $user->idIssuedAt,
            "idExpiryDate" => $input['idExpiryDate'] ?? $user->idExpiryDate,
            "idIssueDate" => $input['idIssueDate'] ?? $user->idIssueDate,
            "user_type" => $input['user_type'] ?? $user->user_type,
            "profile_photo_path" => $input['profile_photo_path'] ?? $user->profile_photo_path,
        ]));

        $user->save();

        // if ($user->registration_country == null) {
        //     $ip = request()->ip();
        //     $response = Http::get("https://ipinfo.io/{$ip}/country");
        //     // var_dump($response->json());
        //     if (Auth::check() && Auth::user()->registration_country === null) {
        //         if ($response->successful()) {
        //             $countryIso2 = $response->json()['country'];
        //             // echo $countryIso2;
        //             $user->registration_country = get_iso3_by_iso2($countryIso2);
        //             $user->save();
        //         }
        //     }
        // }

        // Check if verification document file was provided
        if (isset($input['verificationDocument']) && $input['verificationDocument'] instanceof UploadedFile) {
            $user->update([
                'verificationDocument' => save_image($input['name'] . "/document/", $input['verificationDocument'])
            ]);
        }

        if (isset($input['profile_photo_path'])) {
            $user->updateProfilePhoto($input['profile_photo_path']);
        }

        return get_success_response(['message' => 'Profile updated successfully', 'user' => $user]);
    }

    public function generateAppSecret()
    {
        try {
            $passy = generate_uuid();
            $user = auth()->user();
            $user->password = bcrypt($passy);
            if ($user->save()) {
                return get_success_response([
                    'message' => 'App secret generated successfully',
                    'app_secret' => $passy,
                ]);
            }
        } catch (\Throwable $e) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Handles social login for the application.
     *
     * @param Request $request The incoming request.
     * @param string|null $social The social provider (e.g. 'google').
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function socialLogin(Request $request, $social = null)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required',
                'access_token' => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }


            $is_registered = false;
            $social_url = "https://www.googleapis.com/oauth2/v1/userinfo?access_token={$request->access_token}";
            $google = Http::get($social_url)->json();
            $curl = (object) $google;

            if ($curl->email and password_verify($curl->email, $request->email)) {
                $user = User::where('email', $curl->email)->first();
                if ($user) {
                    $is_registered = true;
                }
                $user = User::updateOrCreate([
                    'email' => $curl->email,
                ], [
                    'name' => $curl->name,
                    'email' => $curl->email,
                    'profile_photo_path' => $curl->picture,
                    'firstName' => $curl->given_name,
                    'lastName' => $curl->family_name,
                    'raw_data' => $curl
                ]);
                $token = Auth::login($user);
                if ($token === false) {
                    return get_error_response(['error' => 'Unauthorized'], 401);
                }
                $loginToken = $this->respondWithToken($token);
                return get_success_response([$loginToken, "is_registered" => $is_registered]);
            }
            return get_error_response(['error' => 'Invalid social login'], 422);
        } catch (\Throwable $th) {
            return get_error_response([
                'error' => $th->getMessage()
            ]);
        }
    }

    public function deleteAccount()
    {
        try {
            $user = auth()->user();
            $user->delete();
            return get_success_response(['message' => 'Account deleted successfully']);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function validateReferrer(Request $request)
    {
        try {
            $ref_code = $request->referred_by;
            $referrer = User::where('referral_code', $ref_code)->first();
            if (!$referrer) {
                return get_error_response('Referrer not found', ['is_valid_referrer' => false]);
            }
            return get_success_response(['is_valid_referrer' => true], 'Referrer found');
        } catch (\Throwable $th) {
            return get_error_response($th->getMessage(), ['error' => $th->getMessage()]);
        }
    }
}
