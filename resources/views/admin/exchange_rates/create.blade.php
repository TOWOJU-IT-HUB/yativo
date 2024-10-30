@extends('layouts.admin')

@section('content')
    <div class="container mx-auto px-4">
        <h1 class="text-2xl font-bold mb-4">Create Exchange Rate</h1>

        <form action="{{ route('admin.exchange_rates.store') }}" method="POST"
            class="bg-white dark:bg-slate-800 rounded-lg p-6 ring-1 ring-slate-900/5 shadow-xl">
            @csrf

            <!-- Rate Type Select -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="rate_type">Rate Type</label>
                <select name="rate_type" id="rate_type" class="mt-1 block w-full p-2 border rounded-md" required>
                    <option value="payin">Deposit</option>
                    <option value="payout">Withdrawal</option>
                </select>
            </div>

            <!-- Gateway Select -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="gateway_id">Gateway</label>
                <select name="gateway_id" id="gateway_id" class="mt-1 block w-full p-2 border rounded-md" required>
                    <option value="">Select Gateway</option>
                    @foreach ($payinMethods as $method)
                        <option value="{{ $method->id }}" data-type="payin">{{ $method->method_name }}</option>
                    @endforeach
                    @foreach ($payoutMethods as $method)
                        <option value="{{ $method->id }}" data-type="payout">{{ $method->method_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Float Percentage Input -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="float_percentage">Float Percentage</label>
                <input type="number" name="float_percentage" id="float_percentage" class="mt-1 block w-full p-2 border rounded-md" required>
            </div>

            <!-- Float Amount Input -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="float_amount">Fixed Amount</label>
                <input type="number" name="float_amount" id="float_amount" class="mt-1 block w-full p-2 border rounded-md" required>
            </div>

            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md">Create</button>
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
