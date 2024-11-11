@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Deposits</h1>
    </div>

    <div class="bg-white dark:bg-boxdark rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Deposit Gateway</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Currency</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-boxdark divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($deposits as $deposit)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors duration-200">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                            {{ $deposit->user?->firstName }} {{ $deposit->user?->lastName }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                            {{ $deposit->depositGateway?->method_name ?? 'N/A - Deleted' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                            $status = $deposit->status;
                            $color = match ($status) {
                                'pending' => 'yellow',
                                'completed' => 'green',
                                'cancelled', 'failed', 'expired' => 'red',
                                'processing' => 'blue',
                                default => 'gray',
                            };
                            @endphp
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $color }}-100 dark:bg-{{ $color }}-900/50 text-{{ $color }}-800 dark:text-{{ $color }}-200">
                                {{ $status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                            {{ $deposit?->currency }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                            {{ $deposit?->amount }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ route('admin.deposits.show', $deposit->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 bg-white dark:bg-boxdark border-t border-gray-200 dark:border-gray-700">
            <div class="flex justify-end">
                {{ $deposits->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
