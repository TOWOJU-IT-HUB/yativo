@extends('layouts.admin')

@section('title', 'Edit Payout Method')
@section('header', 'Edit Payout Method')

@section('content')
    <form action="{{ route('admin.payout-methods.update', $payoutMethod->id) }}" method="POST" class="bg-white dark:bg-boxdark p-6 shadow rounded">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Method Name -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Method Name</label>
                <input type="text" name="method_name" value="{{ $payoutMethod->method_name }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Gateway -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Gateway</label>
                <input type="text" name="gateway" value="{{ $payoutMethod->gateway }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Country -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Country</label>
                <input type="text" name="country" value="{{ $payoutMethod->country }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Currency -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Currency</label>
                <input type="text" name="currency" value="{{ $payoutMethod->currency }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Payment Mode -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Payment Mode</label>
                <input type="text" name="payment_mode" value="{{ $payoutMethod->payment_mode }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Charges Type -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Charges Type</label>
                <select name="charges_type" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
                    <option value="fixed" {{ $payoutMethod->charges_type == 'fixed' ? 'selected' : '' }}>Fixed</option>
                    <option value="percentage" {{ $payoutMethod->charges_type == 'percentage' ? 'selected' : '' }}>Percentage</option>
                    <option value="combined" {{ $payoutMethod->charges_type == 'combined' ? 'selected' : '' }}>Combined</option>
                </select>
            </div>
            <!-- Fixed Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Fixed Charge</label>
                <input type="text" step="any" name="fixed_charge" value="{{ $payoutMethod->fixed_charge }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Float Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Float Charge</label>
                <input type="text" step="any" name="float_charge" value="{{ $payoutMethod->float_charge }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Estimated Delivery -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Estimated Delivery</label>
                <input type="text" name="estimated_delivery" value="{{ $payoutMethod->estimated_delivery }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Pro Fixed Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Pro Fixed Charge</label>
                <input type="text" step="any" name="pro_fixed_charge" value="{{ $payoutMethod->pro_fixed_charge }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Pro Float Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Pro Float Charge</label>
                <input type="text" step="any" name="pro_float_charge" value="{{ $payoutMethod->pro_float_charge }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Minimum Withdrawal -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Minimum Withdrawal</label>
                <input type="text" step="any" name="minimum_withdrawal" value="{{ $payoutMethod->minimum_withdrawal }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Maximum Withdrawal -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Maximum Withdrawal</label>
                <input type="text" step="any" name="maximum_withdrawal" value="{{ $payoutMethod->maximum_withdrawal }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Minimum Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Minimum Charge</label>
                <input type="text" step="any" name="minimum_charge" value="{{ $payoutMethod->minimum_charge }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Maximum Charge -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Maximum Charge</label>
                <input type="text" step="any" name="maximum_charge" value="{{ $payoutMethod->maximum_charge }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Cutoff Hours Start -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Cutoff Hours Start</label>
                <input type="text" step="any" name="cutoff_hrs_start" value="{{ $payoutMethod->cutoff_hrs_start }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Cutoff Hours End -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200">Cutoff Hours End</label>
                <input type="text" step="any" name="cutoff_hrs_end" value="{{ $payoutMethod->cutoff_hrs_end }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>


            <!-- Operating Hours -->
            <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Working Hours Start -->
                <div class="form-group">
                    <label for="Working_hours_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Exchange Rate Float</label>
                    <input type="tel" id="exchange_rate_float" name="exchange_rate_float"
                        value="{{ old('exchange_rate_float', $payoutMethod->exchange_rate_float) }}"
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
                    @error('exchange_rate_float')
                        <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Working Hours End -->
                <div class="form-group">
                    <label for="base_currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Base Currency</label>
                    <input type="text" id="base_currency" name="base_currency"
                        value="{{ old('base_currency', $payoutMethod->base_currency) }}"
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
                    @error('base_currency')
                        <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

        </div>

        <div class="mt-6">
            <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded">Update Payout Method</button>
            <a href="{{ route('admin.payout-methods.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded">Cancel</a>
        </div>
    </form>
@endsection
