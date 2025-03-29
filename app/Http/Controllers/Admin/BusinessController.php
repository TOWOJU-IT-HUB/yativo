<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\UsersDataTable;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Business\VirtualAccount;
use App\Models\BusinessConfig;
use App\Models\Deposit;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Modules\Customer\app\Models\Customer;
use Modules\Customer\app\Models\CustomerVirtualCards;
use Validator;
use Yajra\DataTables\DataTables;
use App\Models\VirtualAccountDeposit;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class BusinessController extends Controller
{
    // public function index(UsersDataTable $dataTable)
    // {
    //     // var_dump($dataTable);
    //     return $dataTable->render('admin/business/index');
    // }


    public function index(Request $request)
    {
        // Filter by user_type if provided
        $query = Business::query();

        if ($request->has('user_type') && $request->user_type != '') {
            switch ($request->user_type) {
                case 'business':
                    $query->whereHas('user', function ($q) {
                        $q->where('user_type', 'business');
                    });
                    break;
                case 'individual':
                    $query->whereHas('user', function ($q) {
                        $q->where('user_type', 'individual');
                    });
                    break;
            }
        }

        $businesses = $query->with('user')->paginate(per_page())->withQueryString();
        return view('admin.business.index', compact('businesses'));
    }

    private function getUserWallets($userId)
    {
        $user = User::findOrFail($userId);
        $wallets = $user->wallets; // Get all wallets of the user
        return $wallets;
    }

    public function show($id)
    {
        $business = Business::whereId($id)->with('user')->first();

        if (!$business || !isset($business->user)) {
            return back()->with('error', 'User data not found');
        }

        $business = Business::whereId($id)->with('user')->first();
        if(!isset($business->user)) {
            return back()->with('error', 'User data not found');
        }
        $user = $business->user;
        $uid = $user->id;
        $customers = Customer::latest()->limit(20)->where('user_id', $uid)->get();

        $virtualAccounts = VirtualAccount::latest()->limit(20)->where('user_id', $uid)->get();
        $virtualCards = CustomerVirtualCards::latest()->limit(20)->where('business_id', $business->id)->get();
        $transactions = TransactionRecord::latest()->limit(20)->where('user_id', $uid)->get();
        $deposits = Deposit::latest()->limit(20)->where('user_id', $uid)->get();
        $withdrawals = Withdraw::latest()->limit(20)->where('user_id', $uid)->get();
        $wallets =  $this->getUserWallets($uid);

        $start_date = now()->startOfMonth(); // First day of the current month
        $end_date = now()->endOfMonth(); // Last day of the current month
        
        // Fetch related data
        $customersThisMonth = Customer::where('user_id', $uid)->whereBetween('kyc_verified_date', [$start_date, $end_date])
            ->where('customer_kyc_status', 'approved')->get();

        $virtualAccountsThisMonth = VirtualAccountDeposit::where('user_id', $uid)->whereBetween('created_at', [$start_date, $end_date])->get();
        // $virtualCards = CustomerVirtualCards::where('user_id', $uid)->whereBetween('created_at', [$start_date, $end_date])->get();
        $transactionsThisMonth = TransactionRecord::where('user_id', $uid)->latest()->limit(20)->get();
        $depositsThisMonth = Deposit::where('user_id', $uid)->whereBetween('created_at', [$start_date, $end_date])->get();
        $withdrawalsThisMonth = Withdraw::where('user_id', $uid)->whereBetween('created_at', [$start_date, $end_date])->get();


        // Analytics data
        $analytics = [
            "count" => [
                "deposit" => $depositsThisMonth->count(),
                "withdrawals" => $withdrawalsThisMonth->count(),
                "virtual_account" => $virtualAccounts->count(),
                "virtual_cards" => $virtualCards->count(),
                "customers" => $customers->count(),
            ],
            "sum" => [
                "total_deposit" => $deposits->sum('amount'),
                "total_withdrawals" => $withdrawals->sum('amount'),
            ],
            "fee_due" => [
                "customers_This_Month" => $customersThisMonth->count() * 3, // $3 is the fee per customer KYC
                "virtualAccounts_This_Month" => $virtualAccountsThisMonth->count() * 2 // $2 is the fee charged for each account that receives money per month
            ]
        ];

        return view('admin.business.show', compact(
            "analytics", 
            "user",
            "business",
            "customers",
            "virtualAccounts",
            "virtualCards",
            "transactions",
            "deposits",
            "withdrawals",
            "wallets",
        ));
    }


    /**
     * Update business prefences
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
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

            // Retrieve the authenticated user's business configuration
            $user = $request->user();
            $businessConfig = $user->businessConfig;

            // If no business configuration exists, create a default one
            if (!$businessConfig) {
                $businessConfig = BusinessConfig::create([
                    'user_id' => $user->id,
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

            // Update the specified key-value pair in the configs
            $configs = $businessConfig->configs;
            $configs[$request->key] = $request->value;
            $businessConfig->configs = $configs;

            // Save the updated business configuration
            if ($businessConfig->save()) {
                return get_success_response(['success' => "Preference updated successfully"]);
            }

            return get_error_response(['error' => 'Unable to update data']);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function approve_business($userId)
    {
        $user = User::findorfail($userId);
        if ($user) {
            $user->update([
                'kyc_status' => 'approved'
            ]);
            return redirect()->back()->with('success', 'Business approved successfully');
        }
        return redirect()->back()->with('error', 'Business not found');
    }
}
