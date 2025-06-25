<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business\VirtualAccount;
use App\Models\CryptoWallets;
use App\Models\TransactionRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\Customer;
use Illuminate\Support\Facades\Crypt;
use Modules\Customer\app\Models\CustomerVirtualCards;
use Log;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $where = [
                'user_id' => active_user(),
            ];

            $query = Customer::where($where);

            // Filter by KYC status
            if ($request->has('kyc_status')) {
                $query->where('customer_kyc_status', $request->customer_kyc_status);
            }

            // Filter by country
            if ($request->has('country')) {
                $query->where('customer_country', $request->country);
            }

            // Search by email
            if ($request->has('email')) {
                $query->where('customer_email', 'LIKE', '%' . $request->email . '%');
            }

            // // Filter by last transaction range
            // if ($request->has('transaction_from') && $request->has('transaction_to')) {
            //     $query->whereHas('transactions', function($q) use ($request) {
            //         $q->whereBetween('created_at', [
            //             $request->transaction_from,
            //             $request->transaction_to
            //         ]);
            //     });
            // }

            $customers = $query->paginate(per_page($request->per_page ?? 20));
            return paginate_yativo($customers);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
    public function storeV1(Request $request)
    {
        // try {
        //     // Validation rules
        //     $rules = [
        //         'customer_name' => 'required',
        //         'customer_email' => 'required|email',
        //         'customer_phone' => 'required',
        //         // 'customer_country' => 'required',
        //         // 'customer_address' => 'required|array',
        //         // 'customer_idType' => 'required',
        //         // 'customer_idNumber' => 'required',
        //         // 'customer_idCountry' => 'required',
        //         // 'customer_idExpiration' => 'required',
        //         // 'customer_idFront' => 'required', // Base64 image
        //         // 'customer_idBack' => 'required',  // Base64 image
        //     ];

        //     // Validate request
        //     $validator = Validator::make($request->all(), $rules);

        //     if ($validator->fails()) {
        //         return get_error_response(['error' => $validator->errors()->toArray()]);
        //     }

        //     // Check if the customer email already exists for the authenticated user
        //     $emailExists = Customer::where([
        //         'customer_email' => $request->customer_email,
        //         'user_id' => auth()->id(),
        //     ])->exists();

        //     if ($emailExists) {
        //         return get_error_response(['error' => ['customer_email' => 'Customer email already exists.']], 422);
        //     }

        //     // make a request to bridge to get KYC link for customer
        //     $response = Http::withHeaders([
        //         'Content-Type' => 'application/json',
        //         'Api-Key' => env('BRIDGE_API_KEY'),
        //         'Idempotency-Key' => generate_uuid()
        //     ])->post(env('BRIDGE_BASE_URL') . 'v0/kyc_links', [
        //                 'full_name' => $request->customer_name,
        //                 'email' => $request->customer_email,
        //                 'type' => 'individual',
        //                 'endorsements' => ['sepa'],
        //                 'redirect_uri' => $request->redirect_uri ?? env('WEB_URL'),
        //             ]);

        //     if ($response->failed()) {
        //         return get_error_response(['error' => $response->json()]);
        //     }

        //     $customer = new Customer();
        //     $customer->customer_name = $request->customer_name;
        //     $customer->customer_email = $request->customer_email;
        //     $customer->customer_phone = $request->customer_phone;
        //     // if ($customer->customer_phone) {
        //     //     $customer->customer_phone = $this->formatPhoneNumber($customer->customer_phone);
        //     // }

        //     if ($customer->save()) {
        //         $customer->customer_kyc_status = 'pending';
        //         $customer->customer_kyc_link = $response->json()['kyc_link'];
        //         $customer->customer_kyc_link_id = $response->json()['id'];
        //         $customer->save();
        //     }

        //     return get_success_response($response->json());

        // } catch (\Throwable $th) {
        //     if(env('APP_ENV') == 'local') {
        //         return get_error_response(['error' => $th->getMessage()]);
        //     }
        //     return get_error_response(['error' => 'Something went wrong, please try again later']);
        // }
    }

    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'customer_name' => 'required',
                'customer_email' => 'required',
                'customer_phone' => 'required',
                'customer_type' => 'required',
                // "send_kyc_mail" => 'sometimes|boolean|in:true,false,0,1',
                'customer_country' => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $validate->customer_id = generate_uuid();
            // echo 1;
            $where = [
                'customer_email' => $request->customer_email,
                'user_id' => auth()->id()
            ];

            $cust = Customer::where($where)->count();
            // echo 2;
            if ($cust > 0) {
                $validator = Validator::make([], []); // Create an empty validator instance
                $validator->errors()->add('customer_email', 'Customer email already exists.');

                return get_error_response($validator->errors()->toArray(), 422);
            }

            $customer = new Customer();
            $customer->user_id = auth()->id();
            $customer->customer_id = generate_uuid();
            $customer->customer_name = $request->customer_name;
            $customer->customer_email = $request->customer_email;
            $customer->customer_phone = $request->customer_phone;
            $customer->customer_status = 'active';
            $customer->customer_country = $request->customer_country;
            // echo 3;
            if ($customer->save()) {
                // echo 4;
                return get_success_response($customer, 201);
            } else {
                // echo 5;
                return get_error_response(['error' => 'Failed to create customer.']);
            }
            // echo 6;
        } catch (\Throwable $th) {
            echo 7;
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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

            return get_success_response($customer, 200, "Customer retrieved successfully");
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
            $accounts = VirtualAccount::where($where)->latest()->limit(5)->get();
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

    public function enrolCustomerForBridgeApi(Request $request)
    {
        //
    }

    public function initKyc($customerId)
    {        
        $cust = Customer::where('customer_id', $customerId)->first();
        
        if (!$cust) {
            return get_error_response(['error' => 'Invalid customer ID provided']);
        }

        $kyc_link = $cust->customer_kyc_link;
        return redirect()->away($kyc_link);
        // return view('kyc.index', compact('kyc_link'));
    }

    public function getCustomerKycLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "customer_id" => "required|exists:customers,customer_id",
            "customer_type" => "required|in:individual,business",
            "redirect_uri" => "sometimes"
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors(), 422);
        }

        $cust = Customer::where('customer_id', $request->customer_id)->first();

        if(!empty($cust->customer_kyc_link)) {
            return get_success_response([
                "kyc_url" => route('checkout.kyc.init', ['customerId' => $request->customer_id])
            ]);
        }

        $url = env('BRIDGE_BASE_URL');
        $apiKey = env('BRIDGE_API_KEY');
        $headers = [
            "Api-Key" => $apiKey,
            "Accept" => "application/json",
            "Idempotency-Key" => generate_uuid()
        ];

        $payload = [
            'type' => $request->customer_type,
            'full_name' => $cust->customer_name,
            'email' => $cust->customer_email,
            'redirect_uri' => $request->redirect_uri ?? 'https://app.yativo.com'
        ];

        $response = Http::withHeaders($headers)->post("{$url}v0/kyc_links", $payload);

        if ($response->successful()) {
            // On success, get the response body
            $data = $response->json();

            $cust->update([
                "customer_kyc_link" => $data['kyc_link']
            ]);

            return get_success_response([
                "kyc_url" => route('checkout.kyc.init', ['customerId' => $request->customer_id])
            ]);
        } else {
            // On failure, log or return the error
            Log::error('Bridge API error', ['response' => $response->body()]);
            return get_error_response(['error' => 'API Request to failed'], 400);
        }
    }
}
