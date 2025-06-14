@extends('layouts.admin')

@section('content')
<div class="p-6 bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">Add Custom Pricing</h1>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <form method="POST" action="{{ route('admin.custom-pricing.store') }}">
                @csrf
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200">User</label>
                        <select id="user_id" name="user_id" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600" required>
                            <option value="">Select User</option>
                            @foreach($users as $user)
                            <option value="{{ $user->user_id }}">{{ $user->business_operating_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="gateway_type" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Gateway Type</label>
                        <select id="gateway_type" name="gateway_type" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600" required>
                            <option value="">Select Gateway Method</option>
                            <option value="payin">Payin Methods</option>
                            <option value="payout">Payout Methods</option>
                            <option value="virtual_card">Virtual Card</option>
                            <option value="virtual_account">Virtual Account</option>
                        </select>
                    </div>

                    <div>
                        <label for="gateway_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Gateway</label>
                        <select id="gateway_id" name="gateway_id" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600" required>
                            <option value="">Select Gateway</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="fixed_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Fixed Charge</label>
                            <input type="number" step="0.01" id="fixed_charge" name="fixed_charge" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600" required />
                        </div>

                        <div>
                            <label for="float_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Float Charge (%)</label>
                            <input type="number" step="0.01" id="float_charge" name="float_charge" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600" required />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50">
                            Save Pricing
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const gatewaySelect = document.getElementById('gateway_id');

    document.getElementById('gateway_type').addEventListener('change', function () {
        const gatewayType = this.value;
        gatewaySelect.innerHTML = '';

        // Add a default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select Gateway';
        gatewaySelect.appendChild(defaultOption);

        if (gatewayType === 'virtual_card') {
            const cardOptions = [
                'card_creation',
                'topup',
                'charge_back',
                'card_termination',
                'card_decline'
            ];

            cardOptions.forEach(function (opt) {
                const option = document.createElement('option');
                option.value = opt;
                option.textContent = opt.replace(/_/g, ' ').toUpperCase();
                gatewaySelect.appendChild(option);
            });

        } else if (gatewayType === 'virtual_account') {
            const accountOptions = [
                'mxn_usd',
                'usd',
                'eur',
                'mxn',
                'brl'
            ];

            accountOptions.forEach(function (opt) {
                const option = document.createElement('option');
                option.value = opt;
                option.textContent = opt.toUpperCase();
                gatewaySelect.appendChild(option);
            });

        } else if (gatewayType === 'payin' || gatewayType === 'payout') {
            // Fetch from server via AJAX
            $.ajax({
                url: "{{ route('admin.custom-pricing.get.gateways') }}",
                type: "GET",
                data: {
                    gateway_type: gatewayType
                },
                success: function (data) {
                    if (Array.isArray(data)) {
                        data.forEach(function (gateway) {
                            const option = document.createElement('option');
                            option.value = gateway.id;
                            option.textContent = gateway.method_name + ' - ' + gateway.country;
                            gatewaySelect.appendChild(option);
                        });
                    } else {
                        console.error('Unexpected response format');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching gateways:', error);
                }
            });
        }
    });
</script>
@endpush

@endsection
