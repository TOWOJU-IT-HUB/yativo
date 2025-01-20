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

        $businesses = $query->with('user')->paginate(10);
        return view('admin.business.index', compact('businesses'));
    }

    public function show($id)
    {
        $business = Business::whereId($id)->with('user')->first();
        $user = $business->user;
        $customers = Customer::latest()->limit(20)->where('user_id', $user->id)->get();
        $virtualAccounts = VirtualAccount::latest()->limit(20)->where('user_id', $user->id)->get();
        $virtualCards = CustomerVirtualCards::latest()->limit(20)->where('business_id', $business->id)->get();
        $transactions = TransactionRecord::latest()->limit(20)->where('user_id', $user->id)->get();
        $deposits = Deposit::latest()->limit(20)->where('user_id', $user->id)->get();
        $withdrawals = Withdraw::latest()->limit(20)->where('user_id', $user->id)->get();

        // $business = $user;

        // return [
        //     "business" => $business,
        //     "user" => $user,
        //     "customers" => $customers,
        //     "virtualAccounts" => $virtualAccounts,
        //     "virtualCards" => $virtualCards,
        //     "transactions" => $transactions,
        //     "deposits" => $deposits,
        //     "withdrawals" => $withdrawals,
        // ];

        return view('admin.business.show', compact('business', 'user', 'customers', 'virtualAccounts', 'virtualCards', 'transactions', 'deposits', 'withdrawals'));
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
            return get_error_response(['error' => $th->getMessage()]);
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
