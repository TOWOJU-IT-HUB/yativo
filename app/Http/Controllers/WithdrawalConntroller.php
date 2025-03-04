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
use App\Services\PayoutCalculator;

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
            $payout = Withdraw::where($where)->with('beneficiary')->first();
            if(!$payout) {
                return get_error_response(['error' => "Payout not found"]);
            }
            return get_success_response($payout->makeHidden(['raw_data', "user_id", "bridge_id", "bridge_customer_id", "bridge_response"]));
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
            // Add debit_wallet column if missing
            if (!Schema::hasColumn('withdraws', 'customer_receive_amount')) {
                Schema::table('withdraws', function (Blueprint $table) {
                    $table->string('customer_receive_amount')->nullable();
                });
            }
    
            $validate = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0.01',
                'payment_method_id' => 'required|integer',
                'customer_id' => 'sometimes|exists:customers,customer_id',
                'debit_wallet' => 'required|string'
            ]);
    
            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }
    
            $validated = $validate->validated();
            $beneficiary = BeneficiaryPaymentMethod::find($validated['payment_method_id']);
    
            // Validate beneficiary and payment method
            if (!$beneficiary || !$beneficiary->gateway_id) {
                return get_error_response(['error' => "Invalid payment method configuration"]);
            }
    
            $payoutMethod = PayoutMethods::find($beneficiary->gateway_id);
            if (!$payoutMethod) {
                return get_error_response(['error' => "Unsupported withdrawal method"]);
            }
    
            // Validate allowed debit currencies
            $allowedCurrencies = explode(',', $payoutMethod->base_currency);
            if (!in_array($validated['debit_wallet'], $allowedCurrencies)) {
                return get_error_response([
                    'error' => "Supported debit currencies: " . implode(', ', $allowedCurrencies)
                ], 400);
            }
    
            // Calculate payout details
            $calculator = new PayoutCalculator();
            $result = $calculator->calculate(
                $validated['amount'],
                $validated['debit_wallet'],
                $validated['payment_method_id'],
                $payoutMethod->exchange_rate_float
            );
    
            // Validate exchange rate
            if ($result['adjusted_rate'] <= 0) {
                return get_error_response(['error' => 'Invalid exchange rate configuration'], 400);
            }
    
            // Validate withdrawal limits in DEBIT CURRENCY
            if ($validated['amount'] < $payoutMethod->minimum_withdrawal) {
                return get_error_response([
                    'error' => "Minimum withdrawal: " . number_format($payoutMethod->minimum_withdrawal, 2) 
                            . " " . $payoutMethod->currency
                ]);
            }
    
            if ($validated['amount'] > $payoutMethod->maximum_withdrawal) {
                return get_error_response([
                    'error' => "Maximum withdrawal: " . number_format($payoutMethod->maximum_withdrawal, 2)
                            . " " . $payoutMethod->currency
                ]);
            }
    
            // Prepare withdrawal record
            $withdrawalData = [
                'user_id' => auth()->id(),
                'gateway' => $payoutMethod->gateway,
                'gateway_id' => $beneficiary->gateway_id,
                'currency' => $payoutMethod->currency,
                'beneficiary_id' => $validated['payment_method_id'],
                'debit_wallet' => $validated['debit_wallet'],
                'amount' => $validated['amount'],
                "debit_amount" => $result['debit_amount'],
                "send_amount" => "",
                "customer_receive_amount" => "",
                'raw_data' => [
                    "rates" => [
                        'base_rate' => $result['exchange_rate'],
                        'adjusted_rate' => $result['adjusted_rate']
                    ],
                    "fees" => [
                        'total_fee' => $result['total_fee'],
                        'fee_breakdown' => $result['fee_breakdown']
                    ],
                    "amounts" => [
                        "debit_amount" => $result['debit_amount'],
                        'requested' => $validated['amount'],
                        'converted' => $result['total_amount'],
                        'debit_currency' => $validated['debit_wallet']
                    ],
                    "limits" => [
                        'min' => $payoutMethod->minimum_withdrawal,
                        'max' => $payoutMethod->maximum_withdrawal
                    ]
                ]
            ];
    
            $withdrawal = Withdraw::create($withdrawalData);
    
            // Format response data
            $responseData = [
                'withdrawal_id' => $withdrawal->id,
                'status' => $withdrawal->status,
                'debit_amount' => $result['debit_amount'],
                'target_amount' => $result['total_amount'],
                'currency' => $payoutMethod->currency,
                'fees' => [
                    'total' => $result['total_fee'],
                    // 'breakdown' => $result['fee_breakdown']
                ],
                // 'exchange_rate' => [
                //     'base' => $result['exchange_rate'],
                //     'adjusted' => $result['adjusted_rate']
                // ],
                'processed_at' => $withdrawal->created_at
            ];
    
            return get_success_response($responseData, 201, "Withdrawal request initiated successfully");
    
        } catch (\Throwable $th) {
            return get_error_response([
                'error' => $th->getMessage(),
                'trace' => config('app.debug') ? $th->getTrace() : []
            ]);
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
