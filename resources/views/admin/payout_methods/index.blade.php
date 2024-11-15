@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white dark:bg-boxdark rounded-lg p-6 ring-1 ring-slate-900/5 shadow-xl">
        <div class="flex justify-between items-center m-3">
            <h2 class="text-slate-900 dark:text-white text-lg font-semibold">Payout Gateways</h2>
            <a href="{{ route('admin.payout-methods.create') }}"
                class="px-4 py-2 bg-primary hover:bg-primary-focus text-white rounded-lg transition duration-300 ease-in-out">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add New Method
                </span>
            </a>
        </div>
        @if (session('success'))
            <div class="mt-4 text-green-600 dark:text-white">{{ session('success') }}</div>
        @endif

        <table class="w-full table-auto">
            <thead>
                <tr class="bg-gray-50 dark:bg-navy-800">
                    <th class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">Method ID</th>
                    <th class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">Method Name</th>
                    <th class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">Gateway</th>
                    <th class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">Country</th>
                    <th class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">Currency</th>
                    <th class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payoutMethods as $method)
                    <tr class="border-b text-center">
                        <td class="py-2">{{ $method->id }}</td>
                        <td class="py-2">{{ $method->method_name }}</td>
                        <td class="py-2">{{ $method->gateway }}</td>
                        <td class="py-2">{{ $method->country }}</td>
                        <td class="py-2">{{ $method->currency }}</td>
                        <td class="py-2">
                            <a href="{{ route('admin.payout-methods.edit', $method->id) }}" class="text-blue-500">Edit</a>
                            <form action="{{ route('admin.payout-methods.destroy', $method->id) }}" method="POST"
                                style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-6 py-4 bg-white dark:boxdark border-t border-gray-200 dark:border-gray-700">
            <div class="flex justify-end">
                {{ $payoutMethods->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
