<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\City;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\JurisdictionCodes;
use App\Models\PayinMethods;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()], 422);
            }

            $where = [
                "gateway_id" => $request->method_id,
                "rate_type" => $request->method_type,
            ];
            // float_percentage
            $ExchangeRate = ExchangeRate::where($where)->first();
            if ($ExchangeRate) {
                $from_currency = $request->from_currency;
                $to_currency = $request->to_currency;
                $rate = exchange_rates($from_currency, $to_currency);
                $amount = 1;
                // calculate the float rate $rate + $floatRate percentage
                $floatRate = ($rate * $ExchangeRate->float_percentage) / 100 ?? 0;
                $converted_amount = ($amount * $rate) + $floatRate;
                return get_success_response([
                    "from_currency" => $from_currency,
                    "to_currency" => $to_currency,
                    "rate" => $rate,
                    "amount" => $amount,
                    "converted_amount" => $converted_amount,
                ]);
            }
            return get_error_response(['error' => "Exchange is currently unavailable for this currency pair and payout method"], 422);

        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
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

            if ($user->user_type == 'individual') {
                $loc = $user->registration_country;
            } elseif ($user->is_business && $user->user_type == 'business' && isset($business->incorporation_country) && $business->incorporation_country !== null) {
                if (isset($regions[$business->incorporation_country])) {
                    $loc = $regions[$business->incorporation_country]['iso3'];
                }
            } else {
                return get_error_response(['error' => 'Please update your country/country of incorporation (for businesses)']);
            }

            $payinMethods = PayinMethods::whereCountry($loc)->get();
            $country = Country::where('iso3', $loc)->first();
            $iso2 = strtolower($country->iso2);
            $country['flag_svg'] = "https://cdn.yativo.com/{$iso2}.svg";
            return get_success_response(['methods' => $payinMethods, 'flag' => $country]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'An error occurred: ' . $e->getMessage()]);
        }
    }
}
