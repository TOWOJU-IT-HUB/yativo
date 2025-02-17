<?php

namespace App\Http\Controllers;

use App\Models\Gateways;
use App\Models\payoutMethods;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use App\Notifications\WithdrawalNotification;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Currencies\app\Models\Currency;
use Modules\Monnet\app\Services\MonnetServices;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;

/**
 * WithdrawalConntroller handles withdrawal requests.
 */
class WithdrawalConntroller extends Controller
{

    public function getPayoutMethods(Request $request)
    {
        try {
            $methods = payoutMethods::when($request->has('country'), function ($query) use ($request) {
                $query->where('country', $request->country);
            })->when($request->has('currency'), function ($query) use ($request) {
                $query->where('currency', $request->currency);
            })->get();

            return get_success_response($methods);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function payoutFilter(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "country" => "required",
                "currency" => "required",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $filters = [
                "country" => $request->country,
                "currency" => $request->currency,
            ];
            $payout = payoutMethods::where($filters)->get();
            return get_success_response($payout);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Get a paginated list of withdrawals for the authenticated user.
     *
     * @return 
     */
    public function index($arrOnly = false)
    {
        try {
            $r = request();
            $payouts = Withdraw::where('user_id', auth()->id())->with('beneficiary')->latest()->paginate(per_page($r->per_page ?? 10));
            if ($arrOnly) {
                return $payouts;
            }
            return paginate_yativo($payouts);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function show($payoutId)
    {
        try {
            $where = [
                'user_id' => auth()->id(),
                'payout_id' => $payoutId
            ];
            $payout = Withdraw::where($where)->with('beneficiary')->first()->makeHidden(['raw_data']);
            return get_success_response($payout);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Store a new withdrawal request.
     * Restoring the codebase to test vitawallet
     * @param Request $request
     * @return \Response
     */
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                // 'beneficiary_id' => 'sometimes',
                'amount' => 'required',
                'payment_method_id' => 'required',
                'customer_id' => 'sometimes|exists:customers,customer_id',
                'debit_wallet' => 'required'
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $validate = $validate->validated();

            $is_beneficiary = BeneficiaryPaymentMethod::with('user')->where(['id' => $request->payment_method_id])->first();

            if (!$is_beneficiary) {
                return get_error_response(['error' => "Payment method not found"]);
            }

            // check if beneficiary is has a payout method
            if (!isset($is_beneficiary->gateway_id) or (!is_numeric($is_beneficiary->gateway_id))) {
                return get_error_response(['error' => "The selected beneficiary has no valid payout method"]);
            }

            // Get beneficiary payout method
            $payoutMethod = payoutMethods::where('id', $is_beneficiary->gateway_id)->first();

            $allowedCurrencies = [];
            $allowedCurrencies = explode(',', $payoutMethod->base_currency);
            
            if (!in_array($request->debit_wallet, $allowedCurrencies)) {
                return get_error_response([
                    'error' =>  "Allowed debit currencies: " . $payoutMethod->base_currency
                ], 400);
            }
            
            $exchange_rate = get_transaction_rate($payoutMethod->currency, $request->credit_wallet ?? $request->currency, $payoutMethod->id, "payoutMethod");
            
            if (!$exchange_rate || $exchange_rate <= 0) {
                return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
            }
            $exchange_rate = floatval($exchange_rate);
            $deposit_float = floatval($payoutMethod->exchange_rate_float ?? 0);

            // Calculate percentage and add to exchange rate
            $exchange_rate -= ($exchange_rate * $deposit_float / 100);
            
            if(($exchange_rate * $request->amount) < $payoutMethod->minimum_withdrawal) {
                return get_error_response(['error' => "Amount can not be less than ". floatval($payoutMethod->minimum_withdrawal * $exchange_rate)]);
            }

            if(($exchange_rate * $request->amount) > $payoutMethod->maximum_withdrawal) {
                return get_error_response(['error' => "Amount can not be greater than ". floatval($payoutMethod->maximum_withdrawal * $exchange_rate)]);
            }

            if (!$payoutMethod) {
                return get_error_response(['error' => "The choosen withdrawal method is invalid or currently unavailable", "gateway" => $payoutMethod->gateway]);
            }


            $validate['user_id'] = auth()->id();
            $validate['raw_data'] = $request->all();
            $validate['gateway'] = $payoutMethod->gateway;
            $validate['gateway_id'] = $is_beneficiary->gateway_id;
            $validate['currency'] = $payoutMethod->currency;
            $validate['beneficiary_id'] = $validate['payment_method_id'];
            unset($validate['payment_method_id']);
            $create = Withdraw::create($validate);

            return get_success_response($create, 201, "Withdrawal request received and will be processed shortlly.");


            return get_error_response(['error' => 'Unable to create withdrawal, Please contact support']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }


    /**
     * Get the status of a withdrawal.
     * 
     * @param int $payoutsId
     * @return \Response
     */
    public function getWithdrawalStatus($payoutsId)
    {
        try {
            // $monnet = new MonnetServices();
            // return $monnet->payoutStatus($payoutsId);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function payoutMethodsCountries()
    {
        try {
            $countries = PayoutMethods::join('countries', 'countries.iso3', '=', 'payout_methods.country')
                ->select('countries.iso3', 'countries.iso2', 'countries.name')
                ->distinct()
                ->get();
            return get_success_response($countries);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }
}
