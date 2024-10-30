<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business\VirtualAccount;
use App\Models\CryptoWallets;
use App\Models\TransactionRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\Customer;
use Illuminate\Support\Facades\Crypt;
use Modules\Customer\app\Models\CustomerVirtualCards;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $where = [
                'user_id' => active_user(),
                // 'customer_status' => 'active',
            ];
            $customers = Customer::where($where)->paginate(per_page(request()->per_page ?? 20));
            return paginate_yativo($customers);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }


    /**
     * Store a newly created resource in storage.
     * save all customer data for a business
     * 
     * @param Request $request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'customer_name' => 'required',
                'customer_email' => 'required',
                'customer_phone' => 'required',
                'customer_country' => 'required',
                'customer_address' => 'required|array',
                'customer_idType' => 'required',
                'customer_idNumber' => 'required',
                'customer_idCountry' => 'required',
                'customer_idExpiration' => 'required',
                'customer_idFront' => 'required',
                'customer_idBack' => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $validate->customer_id = generate_uuid();

            // encrypt data before inserting into DB

            $validatedData = Validator::make($request->all(), [
                'customer_name' => 'required',
                'customer_email' => 'required|email',
                'customer_phone' => 'required',
                'customer_country' => 'required',
                'customer_address' => 'sometimes|array',
                'customer_idType' => 'sometimes',
                'customer_idNumber' => 'sometimes',
                'customer_idCountry' => 'sometimes',
                'customer_idExpiration' => 'sometimes',
                'customer_idFront' => 'sometimes', // only accept base64 Image
                'customer_idBack' => 'sometimes', // only accept base64 Image
            ]);

            if ($validatedData->fails()) {
                return get_error_response(['error' => $validatedData->errors()]);
            }

            $where = [
                'customer_email' => $request->customer_email,
                'user_id' => auth()->id()
            ];

            $cust = Customer::where($where)->count();

            if ($cust > 0) {
                $validator = Validator::make([], []); // Create an empty validator instance
                $validator->errors()->add('customer_email', 'Customer email already exists.');

                return get_error_response($validator->errors(), 422);
            }

            $customerData = [
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_country' => $request->customer_country,
                'customer_address' => $request->customer_address,
                'customer_idType' => $request->customer_idType,
                'customer_idNumber' => $request->customer_idNumber,
                'customer_idCountry' => $request->customer_idCountry,
                'customer_idExpiration' => $request->customer_idExpiration,
            ];


            $json_data = encryptCustomerData(json_encode($customerData));

            $customer = new Customer();
            $customer->user_id = auth()->id();
            $customer->customer_id = generate_uuid();
            $customer->customer_name = $request->customer_name;
            $customer->customer_email = $request->customer_email;
            $customer->customer_phone = $request->customer_phone;
            $customer->customer_country = $request->customer_country;
            $customer->customer_address = $request->customer_address;
            $customer->customer_idType = encryptCustomerData($request->customer_idType) ?? null;
            $customer->customer_idNumber = encryptCustomerData($request->customer_idNumber) ?? null;
            $customer->customer_idCountry = encryptCustomerData($request->customer_idCountry) ?? null;
            $customer->customer_idExpiration = encryptCustomerData($request->customer_idExpiration) ?? null;
            $customer->customer_idFront = encryptCustomerData($request->customer_idFront) ?? null;
            $customer->customer_idBack = encryptCustomerData($request->customer_idBack) ?? null;
            // $customer->customer_status = 'active';
            $customer->json_data = $json_data;

            if ($customer->save()) {
                return get_success_response($customer, 201);
            } else {
                return get_error_response(['error' => 'Failed to store customer information']);
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        try {
            $where = [
                'customer_id' => $id,
                'user_id' => active_user()
            ];


            $customer = Customer::where($where)->first();

            if (!$customer || is_null($customer)) {
                return get_error_response(['error' => 'Customer not found']);
            }

            $customer['customer_deposit'] = $this->getCustomerDeposit(request());
            $customer['customer_payouts'] = $this->getCustomerPayouts(request());
            $customer['customer_swaps'] = $this->getCustomerSwaps(request());
            $customer['customer_virtualaccounts'] = $this->getCustomerVirtualAccounts(request());
            $customer['customer_virtual_cards'] = $this->getCustomerVirtualCards(request());
            $customer['customer_crypto_wallets'] = $this->getCustomerCryptoWallets(request());

            return get_success_response($customer);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
    

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {

            $validate = Validator::make($request->all(), [
                "customer_name" => "required",
                "customer_email" => "required|email",
                "customer_phone" => "required",
                "customer_country" => "required",
                "customer_address" => "required|array",
                "customer_id" => "required|exists:customers,customer_id",
            ]);

            $where = [
                'customer_id' => $id,
                'user_id' => active_user()
            ];

            $customer = Customer::where($where)->first();

            if (!$customer || is_null($customer)) {
                return get_error_response(['error' => 'Customer not found']);
            }

            /**
             * update records from request
             */
            $customerData = [
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_country' => $request->customer_country,
                'customer_address' => $request->customer_address
            ];

        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function doKyc(Request $request, $id)
    {
        try {
            $validate = Validator::make($request->all(), [
                'customer_kyc_status' => 'required|in:active,rejected,pending',
                'customer_kyc_reject_reason' => 'required_if:customer_kyc_status,rejected',
            ]);

        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {

            $where = [
                'customer_id' => $id,
                'user_id' => active_user()
            ];

            if (Customer::where($where)->delete()) {
                return get_success_response(['message' => "Customer deleted successfully"]);
            }

            return get_error_response(['error' => "Please contact support, we're unable to process your request"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function getCustomerDeposit(Request $request)
    {
        try {
            $where = [
                'user_id' => active_user(),
                'customer_id' => $request->customer_id,
                'transaction_memo' => 'payin',
            ];
            $payouts = TransactionRecord::where($where)->latest()->limit(5)->get();
            return $payouts;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function getCustomerPayouts(Request $request)
    {
        try {
            $where = [
                'user_id' => active_user(),
                'customer_id' => $request->customer_id,
                'transaction_memo' => 'payout',
            ];
            $payouts = TransactionRecord::where($where)->latest()->limit(5)->get();
            return $payouts;
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function getCustomerSwaps(Request $request)
    {
        try {
            $where = [
                'user_id' => active_user(),
                'customer_id' => $request->customer_id,
                'transaction_memo' => 'currency_swap',
            ];
            $swaps = TransactionRecord::where($where)->latest()->limit(5)->get();
            return $swaps;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function getCustomerVirtualAccounts(Request $request)
    {
        try {
            $where = [
                'user_id' => active_user(),
                'customer_id' => $request->customer_id,
            ];
            $accounts = VirtualAccount::whereUserId($where)->latest()->limit(5)->get();
            return $accounts;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function getCustomerVirtualCards(Request $request)
    {
        try {
            $where = [
                'business_id' => active_user(),
                'customer_id' => $request->customer_id,
            ];
            $accounts = CustomerVirtualCards::where($where)->latest()->limit(5)->get();
            return $accounts;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function getCustomerCryptoWallets(Request $request)
    {
        try {
            $where = [
                'user_id' => active_user(),
                'customer_id' => $request->customer_id,
            ];
            $accounts = CryptoWallets::where($where)->latest()->limit(5)->get();
            return $accounts;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }
}
