<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Bavix\Wallet\Models\Wallet;


class DashboardController extends Controller
{
    public function index()
    {
        $users = User::get();
        $deposits = Deposit::get();
        $payouts = Withdraw::get();
        // Get total users count
        $totalUsers = User::count();

        // Get users created in the last 7, 30 days, and 1 year
        $usersLast7Days = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $usersLast30Days = User::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $usersLastYear = User::where('created_at', '>=', Carbon::now()->subYear())->count();

        // Get last 5 deposits and withdrawals
        $lastDeposits = Deposit::latest()->limit(5)->get();
        $lastWithdrawals = Withdraw::latest()->limit(5)->get();

        // Total withdrawals in last 30 days and sum of amount withdrawn
        $withdrawalsLast30Days = Withdraw::where('created_at', '>=', Carbon::now()->subDays(30));
        $totalWithdrawalsLast30Days = $withdrawalsLast30Days->count();
        $sumWithdrawalsLast30Days = $withdrawalsLast30Days->sum('amount');

        // Total deposits in last 7, 30 days, and 1 year (with sum)
        $depositsLast7Days = Deposit::where('created_at', '>=', Carbon::now()->subDays(7));
        $depositsLast30Days = Deposit::where('created_at', '>=', Carbon::now()->subDays(30));
        $depositsLastYear = Deposit::where('created_at', '>=', Carbon::now()->subYear());

        $totalDepositsLast7Days = $depositsLast7Days->count();
        $sumDepositsLast7Days = $depositsLast7Days->sum('amount');

        $totalDepositsLast30Days = $depositsLast30Days->count();
        $sumDepositsLast30Days = $depositsLast30Days->sum('amount');

        $totalDepositsLastYear = $depositsLastYear->count();
        $sumDepositsLastYear = $depositsLastYear->sum('amount');

        // Total user wallet balance, grouped by slug
        $walletBalances = Wallet::selectRaw('slug, SUM(balance) as total_balance')
            ->groupBy('slug')
            ->get();

        // Option to click on total users to open the business page (Assuming a route exists)
        $usersLink = route('admin.businesses.index'); // Modify the route based on your business logic

        return view('admin.dashboard', compact(
            'totalUsers',
            'usersLast7Days',
            'usersLast30Days',
            'usersLastYear',
            'lastDeposits',
            'lastWithdrawals',
            'totalWithdrawalsLast30Days',
            'sumWithdrawalsLast30Days',
            'totalDepositsLast7Days',
            'sumDepositsLast7Days',
            'totalDepositsLast30Days',
            'sumDepositsLast30Days',
            'totalDepositsLastYear',
            'sumDepositsLastYear',
            'walletBalances',
            'usersLink',
            'users',
            'deposits',
            'payouts'
        ));
    }
}
