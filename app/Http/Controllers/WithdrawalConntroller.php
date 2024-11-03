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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Store a new withdrawal request.
     *
     * @param Request $request
     * @return \Response
     */
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'beneficiary_id' => 'required',
                'amount' => 'required',
                'payment_method_id' => 'required'
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $validate = $validate->validated();

            $is_beneficiary = BeneficiaryPaymentMethod::with('beneficiary')->where(['beneficiary_id' => $request->beneficiary_id, 'id' => $request->payment_method_id])->first();

            if (!$is_beneficiary) {
                return get_error_response(['error' => "Beneficiary not found"]);
            }

            // check if beneficiary is has a payout method
            if (!isset($is_beneficiary->gateway_id) or (!is_numeric($is_beneficiary->gateway_id))) {
                return get_error_response(['error' => "The selected beneficiary has no valid payout method"]);
            }

            // Get beneficiary payout method
            $payoutMethod = payoutMethods::where('id', $is_beneficiary->gateway_id)->first();
            
            if($request->amount < $payoutMethod->minimum_withdrawal) {
                return get_error_response(['error' => "Amount can not be less than {$payoutMethod->minimum_withdrawal}"]);
            }

            if($request->amount > $payoutMethod->maximum_withdrawal) {
                return get_error_response(['error' => "Amount can not be greater than {$payoutMethod->maximum_withdrawal}"]);
            }

            if (!$payoutMethod) {
                return get_error_response(['error' => "The choosen withdrawal method is invalid or currently unavailable", "gateway" => $payoutMethod->gateway]);
            }

            unset($validate['payment_method_id']);

            $validate['user_id'] = auth()->id();
            $validate['raw_data'] = $request->all();
            $validate['gateway'] = $payoutMethod->gateway;
            $validate['currency'] = $payoutMethod->currency;
            $create = Withdraw::create($validate);

            return get_success_response($create, 201, "Withdrawal request received and will be processed soon.");

            if ($create) {
                $payout = new PayoutService();
                $checkout = $payout->makePayment($create->id, $payoutMethod->gateway);
                // return response()->json($checkout);
                // if (!is_array($checkout)) {
                //     $checkout = (array)$checkout; 
                // }

                // if (isset($checkout['error'])) {
                //     return get_error_response(['error' => $checkout['error']]);
                // }
                // $create->raw_data = $checkout;
                // $create->save();
                // // user()->notify(new WithdrawalNotification($create));
                // $payout = Withdraw::whereId($create->id)->with('beneficiary')->first();


                // $webhook_url = Webhook::whereUserId(auth()->id())->first();
                // if ($webhook_url) {
                //     WebhookCall::create()->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                //         "event.type" => "withdrawal",
                //         "payload" => $payout
                //     ])->dispatchSync();
                // }

                return get_success_response(['response' => $checkout, 'payload' => $payout->makeHidden('raw_data')->toArray()]);
            }

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
            $monnet = new MonnetServices();
            return $monnet->payoutStatus($payoutsId);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function payoutMethodsCountries()
    {
        try {
            $countries = PayoutMethods::join('countries', 'countries.iso3', '=', 'payout_methods.country')
                ->select('countries.iso3', 'countries.name')
                ->distinct()
                ->get();
            return get_success_response($countries);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
}
