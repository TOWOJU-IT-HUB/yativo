<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\TransactionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepositController extends Controller
{
    public function index(Request $request)
    {
        $query = Deposit::query();
        $query->with('user', 'depositGateway', 'transactions');

        $query->when($request->has('status'), function ($query) use ($request) {
            $query->where('status', $request->status);
        });

        $query->orderBy('created_at', 'desc');
        $deposits = $query->paginate(per_page())->withQueryString();

        return view('admin.deposits.index', compact('deposits'));
    }

    public function show($id)
    {
        $deposit = Deposit::with('user', 'depositGateway', 'transactions')->findOrFail($id);
        return view('admin.deposits.show', compact('deposit'));
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "deposit_id" => "required",
            "deposit_status" => "required"
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $deposit = Deposit::whereId($request->deposit_id)->first();
        if (!$deposit) {
            return back()->with('error', "Selected deposit not found");
        }

        $deposit->status = $request->deposit_status;

        if ($deposit->save()) { 
            // update the status on transactionRecord
            $tranx = TransactionRecord::where('transaction_id', $deposit->id)->where('transaction_memo', 'payin')->first();
            if($tranx) {
                $tranx->update([
                    'transaction_status' => $request->deposit_status
                ]);
            }
            return back()->with('success', "Deposit status updated successfully");
        }

        return back()->with('error', "Unable to update deposit status");
    }
}
