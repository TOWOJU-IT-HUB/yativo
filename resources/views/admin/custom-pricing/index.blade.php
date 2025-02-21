@extends('layouts.admin')

@section('content')
<div class="p-6 bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">Manage Custom Pricing</h1>
            <a href="{{ route('admin.custom-pricing.create') }}" class="px-4 py-2 bg-blue-500 text-white rounded-lg">
                Add Custom Pricing
            </a>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200">Gateway ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200">Fixed Charge</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200">Float Charge</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customPricings as $pricing)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $pricing->user->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $pricing->gateway_id }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">${{ $pricing->fixed_charge }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $pricing->float_charge }}%</td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="{{ route('admin.custom-pricing.destroy', $pricing) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-4">
                {{ $customPricings->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
