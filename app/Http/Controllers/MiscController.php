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
use Str;
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
                return get_error_response(['error' => "Currency must be: {$targetCurrency}"]);
            }
    
            // Check allowed currencies
            $allowedCurrencies = explode(',', $method->base_currency);
            if (!in_array(strtoupper($request->from_currency), array_map('strtoupper', $allowedCurrencies))) {
                return get_error_response([
                    'error' => "Allowed currencies: " . implode(', ', $allowedCurrencies)
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
    
            // Build response format
            return get_success_response([
                "from_currency" => strtoupper($request->from_currency),
                "to_currency" => strtoupper($request->to_currency),
                "rate" => number_format($result['adjusted_rate'], 8),
                "amount" => number_format($request->amount, 8),
                "converted_amount" => "1{$request->from_currency} - " . number_format($result['adjusted_rate'], 8) . " {$request->to_currency}",
                "payout_data" => [
                    "total_transaction_fee_in_from_currency" => number_format($result['fee_breakdown']['float'] + $result['fee_breakdown']['fixed'], 8),
                    "total_transaction_fee_in_to_currency" => number_format($result['total_fee'], 8),
                    "customer_sent_amount" => number_format($request->amount, 8),
                    "customer_receive_amount" => number_format($result['total_amount'] - $result['total_fee'], 8),
                    "customer_total_amount_due" => number_format($result['total_amount'], 8)
                ]
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


}
