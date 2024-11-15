<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\UsersDataTable;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Business\VirtualAccount;
use App\Models\Deposit;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Modules\Customer\app\Models\Customer;
use Modules\Customer\app\Models\CustomerVirtualCards;
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

        $businesses = $query->paginate(10);
        return view('admin.business.index', compact('businesses'));
    }

    public function show($id)
    {
        $user = User::with('business')->first();
        // $business = $user->business?->id;
        $customers = Customer::where('user_id', $user->id)->get();
        $virtualAccounts = VirtualAccount::where('user_id', $user->id)->get();
        $virtualCards = CustomerVirtualCards::where('business_id', $user->id)->get();
        $transactions = TransactionRecord::where('user_id', $user->id)->get();
        $deposits = Deposit::where('user_id', $user->id)->get();
        $withdrawals = Withdraw::where('user_id', $user->id)->get();
        $business = $user;

        return view('admin.business.show', compact('business', 'customers', 'virtualAccounts', 'virtualCards', 'transactions', 'deposits', 'withdrawals'));
    }
}
