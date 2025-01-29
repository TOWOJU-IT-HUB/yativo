@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="p-6 bg-gray-100 dark:bg-boxdark min-h-screen">
    <div class="container mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">Admin Dashboard</h1>

        <!-- Wallet Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-4">
            @foreach($walletBalances as $wallet)
            <div class="p-6 bg-white dark:bg-boxdark shadow-lg rounded-xl">
                <p class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-3">
                    {{ number_format($wallet->total_balance, 2) }}
                </p>
                <p class="text-gray-500 dark:text-gray-400">{{ strtoupper($wallet->slug) }}</p>
            </div>
            @endforeach
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <a href="{{ $usersLink }}" class="p-6 bg-white dark:bg-boxdark shadow-lg rounded-xl transition hover:shadow-xl flex items-center">
                <div class="text-blue-600 dark:text-blue-400 text-4xl">
                    <i class="fas fa-users"></i>
                </div>
                <div class="ml-4">
                    <p class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $totalUsers }}</p>
                    <p class="text-gray-500 dark:text-gray-400">Total Users</p>
                </div>
            </a>

            <div class="p-6 bg-white dark:bg-boxdark shadow-lg rounded-xl">
                <p class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $usersLast7Days }}</p>
                <p class="text-gray-500 dark:text-gray-400">Users in Last 7 Days</p>
            </div>

            <div class="p-6 bg-white dark:bg-boxdark shadow-lg rounded-xl">
                <p class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $usersLast30Days }}</p>
                <p class="text-gray-500 dark:text-gray-400">Users in Last 30 Days</p>
            </div>

            <div class="p-6 bg-white dark:bg-boxdark shadow-lg rounded-xl">
                <p class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $usersLastYear }}</p>
                <p class="text-gray-500 dark:text-gray-400">Users in Last Year</p>
            </div>

            <div class="p-6 bg-white dark:bg-boxdark shadow-lg rounded-xl">
                <p class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $totalWithdrawalsLast30Days }}</p>
                <p class="text-gray-500 dark:text-gray-400">Withdrawals in Last 30 Days</p>
                <p class="text-gray-700 dark:text-gray-300 font-bold">
                    {{ strtoupper($wallet->slug) }}
                    {{ number_format($sumWithdrawalsLast30Days, 2) }}
                </p>
            </div>

            <div class="p-6 bg-white dark:bg-boxdark shadow-lg rounded-xl">
                <p class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $totalDepositsLast30Days }}</p>
                <p class="text-gray-500 dark:text-gray-400">Deposits in Last 30 Days</p>
                <p class="text-gray-700 dark:text-gray-300 font-bold">
                    {{ strtoupper($wallet->slug) }}
                    {{ number_format($sumDepositsLast30Days, 2) }}
                </p>
            </div>
        </div>

        <!-- Last 5 Deposits -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
            <div class="bg-white dark:bg-boxdark shadow-lg rounded-xl p-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Last 5 Deposits</h2>
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">
                            <th class="py-2">User</th>
                            <th class="py-2">Amount</th>
                            <th class="py-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lastDeposits as $deposit)
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2 text-gray-800 dark:text-gray-300">{{ $deposit->user->business->business_operating_name }}</td>
                            <td class="py-2 text-green-600 dark:text-green-400">
                                {{ strtoupper($wallet->slug) }}
                                {{ number_format($deposit->amount, 2) }}
                            </td>
                            <td class="py-2 text-gray-700 dark:text-gray-300">{{ $deposit->created_at->format('d M, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection