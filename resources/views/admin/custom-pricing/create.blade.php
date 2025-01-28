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
                        <select id="user_id" name="user_id" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:focus:ring-indigo-500 dark:focus:border-indigo-500" required>
                            <option value="">Select User</option>
                            @foreach($users as $user)
                            <option value="{{ $user->user_id }}">{{ $user->business_operating_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="gateway_type" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Gateway Type</label>
                        <select id="gateway_type" name="gateway_type" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:focus:ring-indigo-500 dark:focus:border-indigo-500" required>
                            <option value="">Select Gateway Method</option>
                            <option value="payin">Payin Methods</option>
                            <option value="payout">Payout Methods</option>
                        </select>
                    </div>

                    <div>
                        <label for="gateway_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Gateway</label>
                        <select id="gateway_id" name="gateway_id" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:focus:ring-indigo-500 dark:focus:border-indigo-500" required>
                            <!-- Gateway options will be dynamically populated based on gateway_type selection -->
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="fixed_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Fixed Charge</label>
                            <input type="number" step="0.01" id="fixed_charge" name="fixed_charge" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:focus:ring-indigo-500 dark:focus:border-indigo-500" required />
                        </div>

                        <div>
                            <label for="float_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Float Charge (%)</label>
                            <input type="number" step="0.01" id="float_charge" name="float_charge" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:focus:ring-indigo-500 dark:focus:border-indigo-500" required />
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
    document.getElementById('gateway_type').addEventListener('change', function() {
        const gatewayType = this.value;
        const gatewaySelect = document.getElementById('gateway_id');

        gatewaySelect.innerHTML = ''; // Clear the current options

        // Add a default option to the select
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select Gateway';
        gatewaySelect.appendChild(defaultOption);

        if (gatewayType) {
            // Use jQuery AJAX to send the request
            $.ajax({
                url: "{{ route('admin.custom-pricing.get.gateways') }}",
                type: "GET",
                data: {
                    gateway_type: gatewayType
                },
                success: function(data) {
                    // Check if data is returned and it's an array
                    if (Array.isArray(data)) {
                        data.forEach(function(gateway) {
                            const option = document.createElement('option');
                            option.value = gateway.id;
                            option.textContent = gateway.name;
                            gatewaySelect.appendChild(option);
                        });
                    } else {
                        console.error('Unexpected response format');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching gateways:', error);
                }
            });
        }
    });
</script>
@endpush

@endsection