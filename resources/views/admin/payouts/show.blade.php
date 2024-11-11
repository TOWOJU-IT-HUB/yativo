@extends('layouts.admin')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Deposit Details</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">ID: {{ $deposit->id }}</p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-lg shadow-lg overflow-hidden">
            <!-- Tabs Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex -mb-px" x-data="{ activeTab: 'deposit' }">
                    <button @click="activeTab = 'deposit'"
                        :class="{ 'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'deposit' }"
                        class="px-6 py-3 border-b-2 text-sm font-medium">
                        Deposit Info
                    </button>
                    <button @click="activeTab = 'user'"
                        :class="{ 'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'user' }"
                        class="px-6 py-3 border-b-2 text-sm font-medium">
                        User Info
                    </button>
                    <button @click="activeTab = 'gateway'"
                        :class="{ 'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'gateway' }"
                        class="px-6 py-3 border-b-2 text-sm font-medium">
                        Gateway Info
                    </button>
                    <button @click="activeTab = 'transactions'"
                        :class="{ 'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'transactions' }"
                        class="px-6 py-3 border-b-2 text-sm font-medium">
                        Transactions
                    </button>
                </nav>
            </div>

            <!-- Tab Contents -->
            <div class="p-6" x-data>
                <!-- Deposit Info Tab -->
                <div x-show="activeTab === 'deposit'" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Amount</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $deposit->amount }} {{ $deposit->currency }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Status</h3>
                            <span
                                class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                            @if ($deposit->status === 'pending') bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200
                            @elseif($deposit->status === 'completed') bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200
                            @else bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200 @endif">
                                {{ $deposit->status }}
                            </span>
                        </div>
                        <!-- Add more deposit info fields -->
                    </div>
                </div>

                <!-- User Info Tab -->
                <div x-show="activeTab === 'user'" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Name</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->firstName }}
                                {{ $deposit->user->lastName }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Email</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->email }}</p>
                        </div>
                        <!-- Add more user info fields -->
                    </div>
                </div>

                <!-- Gateway Info Tab -->
                <div x-show="activeTab === 'gateway'" class="space-y-4">
                    @if ($deposit->deposit_gateway)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($deposit->deposit_gateway->toArray() as $key => $value)
                                        <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {{ ucwords(str_replace('_', ' ', $key)) }}
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ is_array($value) ? json_encode($value) : $value }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                            No gateway information available
                        </div>
                    @endif
                </div>

                <!-- Transactions Tab -->
                <div x-show="activeTab === 'transactions'" class="space-y-4">
                    @if ($deposit->transactions && $deposit->transactions->count() > 0)
                        @foreach ($deposit->transactions as $transaction)
                            <div class="bg-gray-50 dark:bg-slate-800 rounded-lg p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @foreach ($transaction->toArray() as $key => $value)
                                        @if ($key === 'transaction_payin_details')
                                            <div class="col-span-2 space-y-1">
                                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ ucwords(str_replace('_', ' ', $key)) }}
                                                </h4>
                                                @if (is_array($value))
                                                    @foreach ($value as $payinItem)
                                                        @if (is_array($payinItem))
                                                            <div class="bg-white dark:bg-slate-700 p-4 rounded-lg mt-2">
                                                                @foreach ($payinItem as $payinKey => $payinValue)
                                                                    <div class="grid grid-cols-2 gap-2 mb-2">
                                                                        <span
                                                                            class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                            {{ ucwords(str_replace('_', ' ', $payinKey)) }}:
                                                                        </span>
                                                                        <span
                                                                            class="text-sm text-gray-600 dark:text-gray-400">
                                                                            {{ $payinValue }}
                                                                        </span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                                @if (filter_var($payinItem, FILTER_VALIDATE_URL))
                                                                    <a href="{{ $payinItem }}" target="_blank"
                                                                        class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                                        Payment Link
                                                                    </a>
                                                                @else
                                                                    {{ $payinItem }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </div>
                                        @else
                                            <div class="space-y-1">
                                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ ucwords(str_replace('_', ' ', $key)) }}
                                                </h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                                    {{ is_array($value) ? json_encode($value) : $value }}
                                                </p>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                            No transactions available
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

@endsection
