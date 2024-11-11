<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $query = Withdraw::query();
        $query->with('user', 'depositGateway', 'transactions');
        $query->when($request->has('status'), function ($query) use ($request) {
            $query->where('status', $request->status);
        });

        $query->orderBy('created_at', 'desc');
        $payouts = $query->cursorPaginate(10);


        return view('admin.payouts.index', compact('payouts'));
    }

    public function show($id)
    {
        $deposit = Withdraw::with('user', 'payoutGateway', 'transactions')->findOrFail($id);
        return view('admin.payouts.show', compact('payout'));
    }
}
