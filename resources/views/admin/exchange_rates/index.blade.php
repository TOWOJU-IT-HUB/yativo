@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white dark:bg-boxdark rounded-lg shadow-md transition-all duration-300">
        <!-- Header Section -->
        <div class="flex justify-between items-center p-6 border-b border-gray-200 dark:border-navy-600">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">Exchange Rate Floats</h2>
            <a href="{{ route('admin.exchange_rates.create') }}"
                class="px-4 py-2 bg-primary hover:bg-primary-focus text-white rounded-lg transition duration-300 ease-in-out">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add New Method
                </span>
            </a>
        </div>

        <!-- Success Message -->
        @if (session('success'))
            <div class="m-6 p-4 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <!-- Table Section -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-navy-800">
                            <th class="px-4 py-3 text-left text-gray-700 dark:text-white">Gateway</th>
                            <th class="px-4 py-3 text-left text-gray-700 dark:text-white">Rate Type</th>
                            <th class="px-4 py-3 text-left text-gray-700 dark:text-white">Float Percentage</th>
                            <th class="px-4 py-3 text-left text-gray-700 dark:text-white">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-navy-600">
                        @foreach ($exchangeRates as $exchangeRate)
                            <tr class="hover:bg-gray-50 dark:hover:bg-navy-800 transition-colors duration-200">
                                <td class="px-4 py-3 text-gray-600 dark:text-white">
                                    @if($exchangeRate->rate_type == 'payout')
                                        <span class="text-green-500">
                                            {{ ucfirst($exchangeRate->payout?->gateway) }} 
                                            {{ $exchangeRate->payout?->method_name }} 
                                            ({{ $exchangeRate->payout?->country }} - {{ $exchangeRate->payout?->currency }})
                                        </span>
                                    @elseif($exchangeRate->rate_type == 'payin')
                                        <span class="text-red-500">
                                            {{ ucfirst($exchangeRate->payin?->gateway) }} 
                                            {{ $exchangeRate->payin?->method_name }} 
                                            ({{ $exchangeRate->payin?->country }} - {{ $exchangeRate->payin?->currency }})
                                        </span>
                                    @else
                                        <span class="text-yellow-500">N/A - Unknown</span>
                                    @endif
                                </td>                                
                                <td class="px-4 py-3 text-gray-600 dark:text-white">{{ $exchangeRate->rate_type }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-white">{{ $exchangeRate->float_percentage }}</td>
                                <td class="px-4 py-3 flex gap-3">
                                    <a href="{{ route('admin.exchange_rates.edit', $exchangeRate->id) }}"
                                        class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.exchange_rates.destroy', $exchangeRate->id) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                            class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            onclick="return confirm('Are you sure you want to delete this method?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:boxdark">
            <div class="flex justify-end">
                {{ $exchangeRates->links() }}
            </div>
        </div>
    </div>
</div>
@endsection