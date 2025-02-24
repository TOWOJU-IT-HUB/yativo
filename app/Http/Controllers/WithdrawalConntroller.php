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
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

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
            // Check if 'debit_wallet' column exists in the withdraws table, if not, add it
            if (!Schema::hasColumn('withdraws', 'debit_wallet')) {
                Schema::table('withdraws', function (Blueprint $table) {
                    $table->string('debit_wallet')->nullable();
                });
            }
    
            $validate = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1',
                'payment_method_id' => 'required',
                'customer_id' => 'sometimes|exists:customers,customer_id',
                'debit_wallet' => 'required|string'
            ]);
    
            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }
    
            $validated = $validate->validated();
            $beneficiary = BeneficiaryPaymentMethod::find($validated['payment_method_id']);
    
            if (!$beneficiary) {
                return get_error_response(['error' => "Payment method not found"]);
            }
    
            if (empty($beneficiary->gateway_id) || !is_numeric($beneficiary->gateway_id)) {
                return get_error_response(['error' => "The selected beneficiary has no valid payout method"]);
            }
    
            $payoutMethod = PayoutMethods::find($beneficiary->gateway_id);
            if (!$payoutMethod) {
                return get_error_response(['error' => "The chosen withdrawal method is invalid or currently unavailable"]);
            }
    
            $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
            if (!in_array($validated['debit_wallet'], $allowedCurrencies)) {
                return get_error_response([
                    'error' => "Allowed debit currencies: " . implode(', ', $allowedCurrencies)
                ], 400);
            }
    
            // Calculate using PayoutCalculator
            $calculator = new PayoutCalculator();
            $result = $calculator->calculate(
                $validated['amount'],
                $validated['debit_wallet'],
                $validated['payment_method_id'],
                $payoutMethod->exchange_rate_float
            );
    
            if ($result['adjusted_rate'] <= 0) {
                return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
            }
    
            $convertedAmount = $result['total_amount'] - $result['total_fee'];
            $minWithdrawal = $payoutMethod->minimum_withdrawal;
            $maxWithdrawal = $payoutMethod->maximum_withdrawal;
    
            if ($convertedAmount < $minWithdrawal) {
                return get_error_response([
                    'error' => "The minimum withdrawable amount is " . number_format($minWithdrawal, 2) . " " . $beneficiary->currency
                ]);
            }
    
            if ($convertedAmount > $maxWithdrawal) {
                return get_error_response([
                    'error' => "The maximum withdrawable amount is " . number_format($maxWithdrawal, 2) . " " . $beneficiary->currency
                ]);
            }
    
            // Prepare withdrawal data
            $validated['user_id'] = auth()->id();
            $validated['gateway'] = $payoutMethod->gateway;
            $validated['gateway_id'] = $beneficiary->gateway_id;
            $validated['currency'] = $payoutMethod->currency;
            $validated['beneficiary_id'] = $validated['payment_method_id'];
            
            // Calculate fee details
            $transaction_fee = $result['total_fee'];
            $total_amount_charged = $result['total_amount'];
            $transaction_fee_in_debit = $transaction_fee / $result['exchange_rate'];
            $total_amount_charged_in_debit = $result['debit_amount'];
    
            $validated['raw_data'] = [
                "incoming_request" => $request->all(),
                "deposit_float" => $payoutMethod->exchange_rate_float,
                "exchange_rate" => $result['exchange_rate'],
                "adjusted_rate" => $result['adjusted_rate'],
                "minWithdrawal" => $minWithdrawal,
                "maxWithdrawal" => $maxWithdrawal,
                "convertedAmount" => $convertedAmount,
                "transaction_fee" => $transaction_fee,
                "total_amount_charged" => $total_amount_charged,
                'transaction_fee_in_debit_currency' => $transaction_fee_in_debit,
                'total_amount_charged_in_debit_currency' => $total_amount_charged_in_debit,
                'debit_currency' => $request->debit_wallet
            ]; 
            unset($validated['payment_method_id']);
    
            $userData = [
                "beneficiary" => $beneficiary,
                "exchange_rate" => $result['exchange_rate'],
                "adjusted_rate" => $result['adjusted_rate'],
                "transaction_fee" => $transaction_fee,
                "total_amount_charged" => $total_amount_charged,
                'transaction_fee_in_debit_currency' => $transaction_fee_in_debit,
                'total_amount_charged_in_debit_currency' => $total_amount_charged_in_debit,
                'debit_currency' => $request->debit_wallet
            ];
    
            // Create withdrawal
            $create = Withdraw::create($validated);
            return get_success_response(array_merge($create->toArray(), ['payout_data' => $userData]), 201, "Withdrawal request received and will be processed shortly.");
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
