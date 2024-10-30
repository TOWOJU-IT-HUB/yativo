<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $users = User::get();
        $deposits = Deposit::get();
        $payouts = Withdraw::get();
        return view('admin.dashboard', compact('users', 'deposits', 'payouts'));
    }
}
