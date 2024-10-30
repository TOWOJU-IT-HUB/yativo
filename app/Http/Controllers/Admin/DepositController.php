<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function index()
    {
        $deposits = Deposit::with('user', 'depositGateway')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.deposits.index', compact('deposits'));
    }

    public function show($id)
    {
        $deposit = Deposit::with('user', 'depositGateway', 'transaction')->findOrFail($id);
        return view('admin.deposits.show', compact('deposit'));
    }
}
