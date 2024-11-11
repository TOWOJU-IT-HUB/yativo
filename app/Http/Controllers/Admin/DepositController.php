<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use Illuminate\Http\Request;

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
        $deposits = $query->paginate(10);

        return view('admin.deposits.index', compact('deposits'));
    }

    public function show($id)
    {
        $deposit = Deposit::with('user', 'depositGateway', 'transactions')->findOrFail($id);
        return view('admin.deposits.show', compact('deposit'));
    }
}
