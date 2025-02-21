<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WithdrawalConntroller;
use App\Jobs\BulkPayout;
use App\Models\BatchPayout;
use App\Models\payoutMethods;
use App\Models\Withdraw;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;


class WithdrawalController extends Controller
{
    public function singlePayout(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'beneficiary_id' => 'sometimes',
                'amount' => 'required',
                'beneficiary_details_id' => 'required'
            ]);

            $request->merge([
                'payment_method_id' => $request->beneficiary_details_id,
                'user_id' => auth()->id()
            ]);

            if ($validate->fails()) {
                return get_error_response($validate->errors()->toArray());
            }

            $payout = new WithdrawalConntroller;
            $process = $payout->store($request);
            return $process;
        } catch (\Exception $e) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function bulkPayout(Request $request)
    {
        try {
            // Validate the batch withdrawal request
            $validate = Validator::make($request->all(), [
                'payouts' => 'required|array',
                'payouts.*.beneficiary_id' => 'sometimes|numeric',
                'payouts.*.amount' => 'required|numeric|min:1',
                'payouts.*.beneficiary_details_id' => 'required|numeric'
            ]);

            if ($validate->fails()) {
                return get_error_response($validate->errors()->toArray());
            }

            $results = [];
            $errors = [];

            DB::transaction(function () use ($request, &$results, &$errors) {
                foreach ($request->payouts as $index => $withdrawalRequest) {
                    $withdrawalRequest['payment_method_id'] = $request->beneficiary_details_id;
                    try {
                        // Check if the beneficiary has a valid payout method
                        $is_beneficiary = BeneficiaryPaymentMethod::with('beneficiary')
                            ->where([
                                // 'beneficiary_id' => $withdrawalRequest['beneficiary_id'],
                                'id' => $withdrawalRequest['payment_method_id']
                            ])->first();

                        if (!$is_beneficiary) {
                            $errors[$index] = ['error' => "Beneficiary not found"];
                            continue;
                        }

                        if (!isset($is_beneficiary->gateway_id) || !is_numeric($is_beneficiary->gateway_id)) {
                            $errors[$index] = ['error' => "The selected beneficiary has no valid payout method"];
                            continue;
                        }

                        // Get beneficiary payout method
                        $payoutMethod = PayoutMethods::where('id', $is_beneficiary->gateway_id)->first();

                        if (!$payoutMethod) {
                            $errors[$index] = ['error' => "The chosen withdrawal method is invalid or currently unavailable"];
                            continue;
                        }

                        // Create the withdrawal data
                        $withdrawalData = [
                            'user_id' => auth()->id(),
                            'beneficiary_id' => $withdrawalRequest['beneficiary_id'] ?? null,
                            'amount' => $withdrawalRequest['amount'],
                            'gateway' => $payoutMethod->gateway,
                            'currency' => $payoutMethod->currency,
                            'raw_data' => json_encode($withdrawalRequest) // Ensure raw_data is stored as JSON
                        ];

                        // Attempt to create the withdrawal
                        $withdrawal = Withdraw::create($withdrawalData);

                        if ($withdrawal) {
                            // Process the payment using the PayoutService
                            $payout = new PayoutService();
                            $checkout = $payout->makePayment($withdrawal->id, $payoutMethod->gateway);
                            if (!is_array($checkout)) {
                                $checkout = (array) $checkout;
                            }

                            if (isset($checkout['error'])) {
                                $errors[$index] = ['error' => $checkout['error']];
                                continue;
                            }

                            // Update the withdrawal record with the response data
                            $withdrawal->raw_data = json_encode($checkout);
                            $withdrawal->save();

                            $results[$index] = $checkout;
                        } else {
                            $errors[$index] = ['error' => 'Unable to create withdrawal, please contact support'];
                        }
                    } catch (\Throwable $th) {
                        // Catch any exceptions and store the error message and stack trace
                        $errors[$index] = ['error' => $th->getMessage(), 'trace' => $th->getTraceAsString()];
                    }
                }
            });

            if (!empty($errors)) {
                return get_error_response(['errors' => $errors], 400);
            }

            return get_success_response($results);

        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }
    }


    public function getPayouts(Request $request)
    {
        try {
            $payouts = (new WithdrawalConntroller())->index(true);
            return paginate_yativo($payouts);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function getPayout($payoutId)
    {
        try {
            return (new WithdrawalConntroller())->show($payoutId);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Retreive batch payouts
     * 
     * @param string batch_id
     * 
     * @return \Response
     */
    public function getBatchPayout(Request $request, $batchId)
    {
        try {
            $batch = BatchPayout::whereUserId("user_id")->where('batch_id', $batchId)->pluck('batch_id');
            $ids = $batch->payout_ids;

            // retrieve withdrawals with the IDs
            $payouts = Withdraw::whereIn('payout_id', $ids)->latest()->get();
            return get_success_response($payouts);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }
}
