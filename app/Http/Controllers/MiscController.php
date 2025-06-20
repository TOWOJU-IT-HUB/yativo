<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\City;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\JurisdictionCodes;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Str, Http;
use App\Services\PayoutCalculator;

class MiscController extends Controller
{
    /**
     * Verify OTP for email verification.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOtp(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return get_error_response(['error' => $validator->errors()->toArray()], 422);
        }

        $user = User::where('email', $request->input('email'))->first();

        if ($user && $user->verification_otp == $request->input('otp')) {
            $user->update(['is_verified' => true, 'verification_otp' => null]);

            return get_success_response(['message' => 'OTP verified successfully'], 200);
        }

        return get_error_response(['error' => 'Invalid OTP'], 422);
    }

    /**
     * Get all countries.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function countries()
    {
        try {
            $countries = Cache::rememberForever('all_countries', function () {
                return Country::all();
            });
            return get_success_response($countries);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Get all states for a country.
     *
     * @param int|null $countryId Filter states by country ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function states($countryId = null)
    {
        try {
            $cacheKey = 'states_country_' . $countryId;
            $states = Cache::rememberForever($cacheKey, function () use ($countryId) {
                $query = State::with('country');
                if ($countryId !== null) {
                    $query->where('country_id', $countryId);
                }
                return $query->get();
            });
            return get_success_response($states);

        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Get all cities for a state.
     *
     * @param int $stateId Filter cities by state ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function city($stateId)
    {
        try {
            $cacheKey = 'cities_state_' . $stateId;
            $cities = Cache::rememberForever($cacheKey, function () use ($stateId) {
                return City::whereStateId($stateId)->with('state')->get();
            });
            return get_success_response($cities);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function jurisdictions()
    {
        try {
            $cacheKey = 'jurisdictions';
            $record = Cache::remember($cacheKey, now()->addDays(0.001), function () {
                return JurisdictionCodes::all();
            });
            return get_success_response($record);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function isPinSet()
    {
        try {
            $user = User::find(auth()->id());
            if (!$user || empty($user->transaction_pin)) {
                return response()->json(["status" => false]);
            }
            return response()->json(["status" => true]);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function exchangeRateFloat(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'from_currency' => 'required',
                'to_currency' => 'required',
                'method_id' => 'required',
                'method_type' => 'required|in:payin,payout',
                'amount' => 'required|numeric|min:0.01'
            ]);
    
            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()], 422);
            }
    
            // Get the payment method
            if($request->method_type == 'payin') {
                $method = PayinMethods::findOrFail($request->method_id);
            } else {
                $method = PayoutMethods::findOrFail($request->method_id);
            }
    
            // Currency validation
            $targetCurrency = strtoupper($request->method_type == 'payout' 
                ? $method->currency 
                : $method->base_currency);
    
            if (strtoupper($request->to_currency) !== $targetCurrency) {
                return get_error_response(['error' => "to_currency must be: {$targetCurrency}"]);
            }
    
            // Check allowed currencies
            $allowedCurrencies = explode(',', $method->base_currency);
            if (!in_array(strtoupper($request->from_currency), array_map('strtoupper', $allowedCurrencies))) {
                return get_error_response([
                    'error' => "Allowed from currencies are: " . implode(', ', $allowedCurrencies)
                ], 400);
            }
    
            // Calculate using PayoutCalculator
            $calculator = new PayoutCalculator();
            $result = $calculator->calculate(
                floatval($request->amount),
                strtoupper($request->from_currency),
                $request->method_id,
                floatval($method->exchange_rate_float ?? 0)
            );
    

            // return response()->json($result); exit;
            $adjusted_rate =  $result['adjusted_rate'];
            if($request->from_currency === $request->to_currency) {
                $adjusted_rate =  1;
            }

            // Build response format
            return get_success_response([
                "from_currency" => strtoupper($request->from_currency),
                "to_currency" => strtoupper($request->to_currency),
                "rate" => number_format($adjusted_rate, 8),
                "amount" => number_format($request->amount, 8),
                "converted_amount" => "1{$request->from_currency} - " . number_format($adjusted_rate, 8) . " {$request->to_currency}",
                "payout_data" => [
                    "total_transaction_fee_in_from_currency" => number_format($result['total_fee']['wallet_currency'] / $adjusted_rate, 8),
                    "total_transaction_fee_in_to_currency" => number_format($result['total_fee']['wallet_currency'], 2),
                    "customer_sent_amount" => number_format($request->amount, 2),
                    "customer_receive_amount" => number_format($request->amount, 2), //number_format($result['customer_receive_amount']['payout_currency'], 8),
                    "customer_total_amount_due" => number_format($result['amount_due'], 2)
                ],
                "gateway" => $method,
                "calculator" => $result
            ]);
    
        } catch (\Throwable $th) {
            $message = env('APP_ENV') === 'local' 
                ? $th->getMessage() 
                : 'Something went wrong, please contact support';
                
            return get_error_response(['error' => $message], 400);
        }
    }

    public function getPayinMethods()
    {
        try {
            $loc = 'ZEE';
            $user = auth()->user();
            $business = Business::whereUserId($user->id)->latest()->first();

            $regions = [
                "US-DE" => [
                    "country" => "U.S.A - Delaware",
                    "iso3" => "USA"
                ],
                "US-CA" => [
                    "country" => "U.S.A - California",
                    "iso3" => "USA"
                ],
                "US-NY" => [
                    "country" => "U.S.A - New York",
                    "iso3" => "USA"
                ],
                "US-NV" => [
                    "country" => "U.S.A - Nevada",
                    "iso3" => "USA"
                ],
                "US-TX" => [
                    "country" => "U.S.A - Texas",
                    "iso3" => "USA"
                ],
                "US-FL" => [
                    "country" => "U.S.A - Florida",
                    "iso3" => "USA"
                ],
                "US-WY" => [
                    "country" => "U.S.A - Wyoming",
                    "iso3" => "USA"
                ],
                "US-MA" => [
                    "country" => "U.S.A - Massachusetts",
                    "iso3" => "USA"
                ],
                "US-IL" => [
                    "country" => "U.S.A - Illinois",
                    "iso3" => "USA"
                ],
                "US-WA" => [
                    "country" => "U.S.A - Washington",
                    "iso3" => "USA"
                ],
                "AR-N" => [
                    "country" => "Argentina",
                    "iso3" => "ARG"
                ],
                "AR-P" => [
                    "country" => "Argentina",
                    "iso3" => "ARG"
                ],
                "BR-N" => [
                    "country" => "Brazil",
                    "iso3" => "BRA"
                ],
                "BR-S" => [
                    "country" => "Brazil",
                    "iso3" => "BRA"
                ],
                "CL-N" => [
                    "country" => "Chile",
                    "iso3" => "CHL"
                ],
                "CO-N" => [
                    "country" => "Colombia",
                    "iso3" => "COL"
                ],
                "MX-N" => [
                    "country" => "Mexico",
                    "iso3" => "MEX"
                ],
                "MX-S" => [
                    "country" => "Mexico",
                    "iso3" => "MEX"
                ],
                "PE-N" => [
                    "country" => "Peru",
                    "iso3" => "PER"
                ],
                "UY-N" => [
                    "country" => "Uruguay",
                    "iso3" => "URY"
                ],
                "CR-N" => [
                    "country" => "Costa Rica",
                    "iso3" => "CRI"
                ],
                "PA-N" => [
                    "country" => "Panama",
                    "iso3" => "PAN"
                ],
                "EC-N" => [
                    "country" => "Ecuador",
                    "iso3" => "ECU"
                ],
                "KY-N" => [
                    "country" => "Cayman Islands",
                    "iso3" => "CYM"
                ]
            ];

            if (isset($regions[$business->incorporation_country])) {
                $loc = $regions[$business->incorporation_country]['iso3'];
            } else {
                return get_error_response(['error' => 'Please update your country/country of incorporation (for businesses)']);
            }

            $payinMethods = PayinMethods::whereCountry($loc)->orWhere('country', 'global')->get();
            $country = Country::where('iso3', $loc)->first();
            $iso2 = strtolower($country->iso2);
            $country['flag_svg'] = "https://cdn.yativo.com/{$iso2}.svg";
            return get_success_response(['methods' => $payinMethods, 'flag' => $country]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'An error occurred: ' . $e->getMessage()]);
        }
    }
    
    public static function uploadBase64ImageToCloudflare($imageBase64)
    {
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64));

        // Generate a unique filename with the appropriate extension
        $fileName = Str::uuid() . '.png';

        // Save the decoded image temporarily
        $tempFilePath = storage_path("app/public/{$fileName}");
        file_put_contents($tempFilePath, $imageData);

        try {
            // Make a POST request to upload the image to Cloudflare
            if ($imgUrl = save_base64_image("bitnob", $tempFilePath)) {
                return $imgUrl;
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to upload image to Cloudflare.',
                ];
            }
        } catch (\Exception $e) {
            // Handle exceptions
            return [
                'success' => false,
                'message' => 'An error occurred while uploading image.',
                'error' => $e->getMessage(),
            ];
        } finally {
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }
    }

    public function validateDocument(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'country' => 'required|string|size:3',
            'document.id' => 'required|string',
            'document.type' => 'required|string',
        ]);

        $baseUrl = "https://api.stage.localpayment.com/api";

        // STEP 1: Get Bearer Token
        $tokenResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($baseUrl.'/token/', [
            'username' => env('LOCALPAYMENT_EMAIL'),
            'password' => env('LOCALPAYMENT_PASSWORD'),
        ]); 

        if (!$tokenResponse->ok()) {
            return response()->json(['error' => 'Unable to fetch bearer token'], 500);
        }

        $token = $tokenResponse->json('access');

        // STEP 2: Make document validation call
        $validationResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->post($baseUrl.'/validation/document', [
            'document' => [
                'id' => $validated['document']['id'],
                'type' => $validated['document']['type'],
            ],
            'country' => $validated['country'],
        ]);

        // return response as boolean
        $validate = $validationResponse->json();
        if($validate['code'] == 200) {
            // charge customer for valid ID.
            
            return get_success_response([
                "valid" => true
            ]);
        }

        return get_error_response(['valid' => false]);
    }
}
