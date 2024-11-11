<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessRequest;
use App\Models\Business;
use App\Models\BusinessConfig;
use App\Models\BusinessUbo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\ShuftiPro\app\Services\ShuftiProServices;

class BusinessController extends Controller
{
    // Store a new business
    public function store(BusinessRequest $request)
    {
        try {
            $validated = $request->validated();
            $validated['user_id'] = auth()->id();
            $validated['ip_address'] = $request->ip();

            if($request->incorporation_country) {
                $jurisdiction = $request->incorporation_country;
            }

            $business = Business::updateOrCreate(
                ['user_id' => $validated['user_id']],
                $validated
            );

            if (!empty($business->business_legal_name)) {
                $user = auth()->user();
                $user->update([
                    'is_kyc_submitted' => true,
                    'membership_id' => uuid(9, "B"),
                    'kyc_status' => null,
                    'user_type' => "business",
                    'bussinessName' => $business->business_operating_name
                ]);
            }

            return get_success_response($business, 201);
            
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 500);
        }
    }

    // Retrieve a business
    public function show()
    {
        try {
            // if (request()->user()->is_business != true) {
            //     return get_error_response(['error' => 'Please enable the Bussiness option in your profile to get access to this feature'], 403);
            // }
            $business = Business::whereUserId(auth()->id())->with(['business_ubo', 'preference'])->first();
            return get_success_response($business, 200);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 404);
        }
    }

    // Update a business
    public function update(BusinessRequest $request)
    {
        try {
            $business = Business::whereUserId(auth()->id())->first();
            $business->update($request->validated());
            return get_success_response($business, 200);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 404);
        }
    }

    // Delete a business
    public function destroy()
    {
        try {
            $business = Business::whereUserId(auth()->id())->first();
            $business->delete();
            return get_success_response(['message' => "Record deleted successfully"], 204);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 404);
        }
    }

    public function verifyBusiness(Request $request)
    {
        // try {
        //     $validate = Validator::make($request->all(), [
        //         'token' =>'required|string|min:32|max:32'
        //     ]);
    }

    /**
     * Send shufti pro verification email
     * to UBO of a business
     * 
     * @return string
     */
    public function sendEmailNotification(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required|email',
                'name' => 'required',
                'business_id' => 'required|numeric',
                'is_admin' => 'required|boolean',
                'is_ubo' => 'required|boolean'
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $saved = false;
            $customerEmail = $request->email;
            $customerName = $request->name;
            $userPosition = $request->position;

            $business = Business::whereUserId($request->business_id)->first();
            $businessName = $business->business_operating_name;

            // check if ubo already exists
            $uboExists = BusinessUbo::where([
                'business_id' => $business->id,
                'ubo_email' => $request->email
            ])->first();

            if ($uboExists && $uboExists->ubo_verification_status != 'pending') {
                return get_error_response(['error' => "UBO verification already process and the current status is: $uboExists->ubo_verification_status"]);
            } elseif (!$uboExists) {
                $shufti = new ShuftiProServices();
                $response = $curl = $shufti->getShuftiUrl(auth()->user());

                if (isset($response['verification_url'])) {
                    $kybUrl = $response['verification_url'];
                }

                if (isset($curl["error"])) {
                    return get_error_response(['error' => $curl['error']['message']]);
                }

                $ubo = new BusinessUbo();
                $ubo->business_id = $business->id;
                $ubo->ubo_name = $request->name;
                $ubo->ubo_email = $request->email;
                $ubo->is_ubo = $request->is_ubo;
                $ubo->is_admin = $request->is_admin;
                $ubo->ubo_verification_url = $kybUrl;
                $ubo->ubo_verification_reference = $response['reference'];
                $saved = $ubo->save();
            } else {
                $customerName = $uboExists->ubo_name;
                $kybUrl = $uboExists->ubo_verification_url;
                $saved = true;
            }

            if ($saved) {
                Mail::send('emails.business-verification', [
                    'name' => $customerName,
                    'position' => $userPosition,
                    'businessName' => $businessName,
                    'kybUrl' => $kybUrl
                ], function ($message) use ($customerEmail) {
                    $message->to($customerEmail)
                        ->subject('Verify Your Business Registration');
                });

                return get_success_response(['message' => 'Email notification sent successfully']);
            }

            return get_error_response(['error' => "Error sending email to $customerName"]);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 
     */
    public function uboList(Request $request)
    {
        try {
            $business_id = get_business_id(auth()->id());
            if (!$business_id) {
                return get_error_response(['error' => 'business not found']);
            }
            $ubos = BusinessUbo::whereBusinessId($business_id)->get();
            if (!empty($ubos)) {
                return get_success_response($ubos);
            }
            return get_error_response(['No UBO found'], 404);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function preference(Request $request)
    {
        try {
            $businessConfig = $request->user()->businessConfig;

            if (!$businessConfig) {
                // If not, create a new one
                $businessConfig = new BusinessConfig([
                    'user_id' => $request->user()->id,
                    'configs' => [
                        "can_issue_visa_card" => false,
                        "can_issue_master_card" => false,
                        "can_issue_bra_virtual_account" => false,
                        "can_issue_mxn_virtual_account" => false,
                        "can_issue_arg_virtual_account" => false,
                        "can_issue_usdt_wallet" => false,
                        "can_issue_usdc_wallet" => false,
                        "charge_business_for_deposit_fees" => false,
                        "charge_business_for_payout_fees" => false,
                        "can_hold_balance" => false,
                        "can_use_wallet_module" => false,
                        "can_use_checkout_api" => false
                    ]
                ]);
            }

            return get_success_response($businessConfig, 200);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }
    }

    public function updatePreference(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'key' => 'required|string',
                'value' => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $businessConfig = $request->user()->businessConfig;

            if (!$businessConfig) {
                // If not, create a new one
                $businessConfig = new BusinessConfig([
                    'user_id' => $request->user()->id,
                    'configs' => [
                        "can_issue_visa_card" => false,
                        "can_issue_master_card" => false,
                        "can_issue_bra_virtual_account" => false,
                        "can_issue_mxn_virtual_account" => false,
                        "can_issue_arg_virtual_account" => false,
                        "can_issue_usdt_wallet" => false,
                        "can_issue_usdc_wallet" => false,
                        "charge_business_for_deposit_fees" => false,
                        "charge_business_for_payout_fees" => false,
                        "can_hold_balance" => false,
                        "can_use_wallet_module" => false,
                        "can_use_checkout_api" => false
                    ]
                ]);
            }

            $configs = $businessConfig->configs;
            $configs[$request->key] = $request->value;
            $businessConfig->configs = $configs;

            if ($businessConfig->save()) {
                return get_success_response(['success' => "Preference updated successfully"]);
            }

            return get_error_response(['error' => 'Unable to update data']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }

    }

}