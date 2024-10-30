@extends('layouts.admin')

@section('title', 'Create Payout Method')
@section('header', 'Create New Payout Method')

@section('content')
    <form action="{{ route('admin.payout-methods.store') }}" method="POST" class="bg-white dark:bg-gray-800 p-6 shadow rounded">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Method Name -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Method Name</label>
                <input type="text" name="method_name" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Gateway -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Gateway</label>
                <input type="text" name="gateway" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Country -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Country</label>
                <input type="text" name="country" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Currency -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Currency</label>
                <input type="text" name="currency" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Payment Mode -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Payment Mode</label>
                <input type="text" name="payment_mode" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Charges Type -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Charges Type</label>
                <input type="text" name="charges_type" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Fixed Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Fixed Charge</label>
                <input type="number" name="fixed_charge" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Float Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Float Charge</label>
                <input type="number" name="float_charge" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Estimated Delivery -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Estimated Delivery</label>
                <input type="text" name="estimated_delivery" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Pro Fixed Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Pro Fixed Charge</label>
                <input type="number" name="pro_fixed_charge" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Pro Float Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Pro Float Charge</label>
                <input type="number" name="pro_float_charge" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Minimum Withdrawal -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Minimum Withdrawal</label>
                <input type="number" name="minimum_withdrawal" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Maximum Withdrawal -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Maximum Withdrawal</label>
                <input type="number" name="maximum_withdrawal" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Minimum Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Minimum Charge</label>
                <input type="number" name="minimum_charge" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Maximum Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Maximum Charge</label>
                <input type="number" name="maximum_charge" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Cutoff Hours Start -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Cutoff Hours Start</label>
                <input type="time" name="cutoff_hrs_start" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>

            <!-- Cutoff Hours End -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Cutoff Hours End</label>
                <input type="time" name="cutoff_hrs_end" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 rounded-md">
            </div>
        </div>

        <div class="mt-6">
            <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded">Save Payout Method</button>
            <a href="{{ route('admin.payout-methods.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded">Cancel</a>
        </div>
    </form>
@endsection
