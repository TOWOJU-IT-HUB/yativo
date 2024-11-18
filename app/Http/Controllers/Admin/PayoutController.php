<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\payoutMethods;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Modules\Customer\app\Models\Customer;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $query = Withdraw::query();
        $query->with('user', 'payoutGateway', 'transactions');
        $query->when($request->has('status'), function ($query) use ($request) {
            $query->where('status', $request->status);
        });

        $query->orderBy('created_at', 'desc');
        $payouts = $query->cursorPaginate(10);


        return view('admin.payouts.index', compact('payouts'));
    }

    public function show($id)
    {
        $payout = Withdraw::with('user', 'transactions', 'payoutGateway')->findOrFail($id);
        if(isset($payout->raw_data['customer_id']) && !empty($payout->raw_data['customer_id'])){
            $payout['customer'] = Customer::whereCustomerId($payout->raw_data['customer_id'])->first();
        }
        // return $payout;
        return view('admin.payouts.show', compact('payout'));
    }
}
