<?php

namespace Modules\Beneficiary\app\Http\Controllers;

use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Controller;
use App\Models\Gateways;
use App\Models\payoutMethods;
use App\Models\UserMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;

class BeneficiaryPaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        try {
            $beneficiaries = BeneficiaryPaymentMethod::with('gateway')->where("user_id", active_user())->latest()->get();
            if (isApi())
                return get_success_response($beneficiaries);

        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Add new Beneficiary, the add the
     * benefiary payment methods
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'gateway_id' => 'required',
                'payment_data' => 'required',
                'beneficiary_id' => 'sometimes',
                'currency' => 'required',
                'nickname' => 'required',
            ]);

            if ($validator->fails()) {
                return get_error_response((array) $validator->errors());
            }

            $user = auth()->user();
            $payload = $request;

            $gateway = payoutMethods::whereId($request->gateway_id)->first();
            $currency = $gateway->currency;

            if ($gateway->gateway == 'bitso' && strtoupper($gateway->currency) == "USD") {
                // Code for bitso USD gateway handling
            } else if ($gateway->gateway == 'bridge') {
                // Code for bridge gateway handling
                $validator = Validator::make($request->all(), [
                    'customer_id' => 'required|exists:customers,customer_id',
                    'payment_data.account_number' => 'required|string',
                    'payment_data.account_name' => 'required|string|max:100',
                    'payment_data.routing_number' => 'required|string',
                    'payment_data.account_type' => 'required|in:us,iban',
                    'payment_data.address.line1' => 'required|string|max:255',
                    'payment_data.address.line2' => 'nullable|string|max:255',
                    'payment_data.address.city' => 'required|string|max:100',
                    'payment_data.address.state' => 'required|string|max:100',
                    'payment_data.address.postal_code' => 'required|string|max:20',
                    'payment_data.address.country' => 'required|string|size:2',
                    // US-specific validations
                    'payment_data.bank_account_number' => 'required_if:account_type,us|string|max:20',
                    'payment_data.bank_routing_number' => 'required_if:account_type,us|string|max:9',
                    'payment_data.checking_or_savings' => 'required_if:account_type,us|in:checking,savings',
                    // IBAN-specific validations
                    'payment_data.iban_account_number' => 'required_if:account_type,iban|string|max:34',
                    'payment_data.iban_bic' => 'required_if:account_type,iban|string|max:11',
                    'payment_data.iban_country' => 'required_if:account_type,iban|string|size:2',
                    'payment_data.account_owner_type' => 'nullable|in:individual,business',
                    'payment_data.first_name' => 'required_if:account_owner_type,individual|string|max:100',
                    'payment_data.last_name' => 'required_if:account_owner_type,individual|string|max:100',
                    'payment_data.business_name' => 'required_if:account_owner_type,business|string|max:255',
                ]);
                if ($validator->fails()) {
                    return get_error_response((array) $validator->errors()->toArray());
                }
                $bridge = new BridgeController();
                $result = $bridge->externalAccounts($validator->validated(), $gateway);

                if(isset($result['error'])) {
                    return get_error_response($result['error']);
                }

                if ($result) {
                    return get_success_response(['message' => "Payment data processed successfully", "data" => $result]);
                }

            } else if ($gateway->gateway == 'local_payment') {
                $result = $this->localPayments($request);
                return get_success_response($result);

            } elseif ($gateway->gateway == 'monnet') {
                if (!in_array($currency, ['PEN', 'MXN'])) {
                    return get_error_response(['error' => 'Invalid or unsupported Currency type']);
                }

                if ($currency == "PEN") {
                    $requirement = [
                        "document_id" => 2,
                        'currency' => 'PEN',
                        'document_value_key' => 'PAS',
                        'document_type_name' => 'International Passport',
                        'document_validation_regex' => '/^\d{7,12}$/'
                    ];
                } else if ($currency == 'MXN') {
                    $requirement = [
                        "document_id" => 4,
                        'currency' => 'MXN',
                        'document_value_key' => 'PAS',
                        'document_type_name' => 'International Passport',
                        'document_validation_regex' => '/^\d{7,18}$/'
                    ];
                }

                $idType = (int) $payload['payment_data']['beneficiary']['document']['type'];
                $idNumber = $payload['payment_data']['beneficiary']['document']['number'];

                if ($idType !== 4 && $idType !== 2) {
                    return get_error_response(['error' => "International passport is the only acceptable means of verification. Contact support for other options"]);
                }

                if (!preg_match($requirement['document_validation_regex'], $idNumber)) {
                    return get_error_response(['error' => 'Invalid Document ID number']);
                }

                $model = new BeneficiaryPaymentMethod;
                $model->user_id = active_user();
                $model->currency = $gateway->currency;
                $model->gateway_id = $request->gateway_id;
                $model->nickname = $request->nickname;
                $model->address = $request->address;
                $model->payment_data = $request->payment_data;
                $model->beneficiary_id = $request->beneficiary_id;
            } else {
                $model = new BeneficiaryPaymentMethod;
                $model->user_id = active_user();
                $model->currency = $gateway->currency;
                $model->gateway_id = $request->gateway_id;
                $model->nickname = $request->nickname ?? null;
                $model->address = $request->address ?? null;
                $model->payment_data = $request->payment_data;
                $model->beneficiary_id = $request->beneficiary_id;
            }

            if ($model->save()) {
                return get_success_response(['message' => "Payment data processed successfully", "data" => $model]);
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function localPayments(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'gateway_id' => 'required',
                'currency' => 'required',
                'payment_data' => 'required',
                'beneficiary_id' => 'sometimes',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $model = new BeneficiaryPaymentMethod;
            $model->user_id = active_user();
            $model->currency = $request->currency;
            $model->gateway_id = $request->gateway_id;
            $model->nickname = $request->nickname;
            $model->address = $request->address;
            $model->payment_data = $request->payment_data;
            $model->beneficiary_id = $request->beneficiary_id;

            $model->payment_data->address = $request->address;
            if ($data = $model->save()) {
                return ['message' => "Payment data processed successfully"];
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function show(BeneficiaryPaymentMethod $model, $id)
    {
        try {
            $payload = $model->getBeneficiaryPaymentMethod($id);
            if (!empty($payload)) {
                return get_success_response($payload);
            }

            return get_error_response(['error' => 'Record not found'], 404);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validate = Validator::make($request->all(), [
                'gateway_id' => 'required',
                'currency' => 'required',
                'payment_data' => 'required',
                "nickname" => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $model = BeneficiaryPaymentMethod::whereId($id)->first();
            $model->user_id = active_user();
            $model->currency = $request->currency;
            $model->gateway_id = $request->gateway_id;
            $model->nickname = $request->nickname;
            $model->address = $request->address;
            $model->payment_data = $request->payment_data;

            $model->payment_data->address = $request->address;
            if ($model->save()) {
                return get_success_response(['message' => "Payment data updated successfully"]);
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $model = BeneficiaryPaymentMethod::find($id);
        if ($model->delete()) {
            return get_success_response(["message" => "Record deleted successfully"]);
        } else {
            return get_error_response(["message" => "Error encountered, unable to delete record"]);
        }
    }
}
