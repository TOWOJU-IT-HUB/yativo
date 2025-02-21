@extends('layouts.admin')

@section('content')
    <div class="container mx-auto px-4">
        <h1 class="text-2xl font-bold mb-4">Create Exchange Rate</h1>

        <form action="{{ route('admin.exchange_rates.store') }}" method="POST"
            class="bg-white dark:bg-boxdark rounded-lg p-6 ring-1 ring-slate-900/5 shadow-xl">
            @csrf

            <!-- Rate Type Select -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="rate_type">Rate Type</label>
                <select name="rate_type" id="rate_type"
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">

                    <option value="payin">Deposit</option>
                    <option value="payout">Withdrawal</option>
                </select>
            </div>

            <!-- Gateway Select -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="gateway_id">Gateway</label>
                <select name="gateway_id" id="gateway_id"
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">

                    <option value="">Select Gateway</option>
                    @foreach ($payinMethods as $method)
                        <option value="{{ $method->id }}" data-type="payin">{{ "$method->method_name ($method->country - $method->currency) " }}</option>
                    @endforeach
                    @foreach ($payoutMethods as $method)
                        <option value="{{ $method->id }}" data-type="payout">{{ "$method->method_name ($method->country - $method->currency) " }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Float Percentage Input -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="float_percentage">Float
                    Percentage</label>
                <input type="number" name="float_percentage" id="float_percentage"
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <!-- Float Amount Input -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="float_amount">Fixed
                    Amount</label>
                <input type="number" name="float_amount" id="float_amount"
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            </div>

            <button type="submit" class="bg-primary dark:bg-purple-100 text-white px-4 py-2 rounded-md">Create</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rateTypeSelect = document.getElementById('rate_type');
            const gatewaySelect = document.getElementById('gateway_id');

            rateTypeSelect.addEventListener('change', function() {
                const selectedType = this.value;
                Array.from(gatewaySelect.options).forEach(option => {
                    if (option.value === '') return; // Skip the placeholder option
                    option.style.display = option.dataset.type === selectedType ? '' : 'none';
                });
                gatewaySelect.value = ''; // Reset selection when changing type
            });

            // Trigger change event on page load to set initial state
            rateTypeSelect.dispatchEvent(new Event('change'));
        });
    </script>
@endsection
