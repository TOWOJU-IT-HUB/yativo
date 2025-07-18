@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Deposit Details</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">ID: {{ $deposit->id }}</p>
    </div>

    <div class="bg-white dark:bg-boxdark rounded-lg shadow-lg overflow-hidden">
        <!-- Tabs Navigation -->
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex -mb-px">
                <button data-tab="deposit" class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                    Deposit Info
                </button>
                <button data-tab="user" class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                    User Info
                </button>
                <button data-tab="gateway" class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                    Gateway Info
                </button> 
                <button data-tab="transactions" class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                    Transactions
                </button>
                <button data-tab="statusChange" class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                    Update Status
                </button>
            </nav>
        </div>

        <!-- Tab Contents -->
        <div class="p-6">
            <div id="deposit" class="tab-content hidden space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($deposit->toArray() as $k => $item)
                        @if (!in_array($k, ['meta', 'raw_data']))  {{-- Exclude meta and raw_data --}}
                            <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ ucwords($k) }}</h3>
                                <p class="text-gray-600 dark:text-gray-300">
                                    @if(is_array($item) || is_object($item))
                                        <pre class="text-sm">{{ json_encode($item, JSON_PRETTY_PRINT) }}</pre>
                                    @else
                                        {{ $item }}
                                    @endif
                                </p>
                            </div>
                        @endif
                    @endforeach

                    @foreach (['meta', 'raw_data'] as $field)
                        @if (!empty($deposit->$field) && (is_array($deposit->$field) || is_object($deposit->$field)))
                            <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg overflow-auto">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ ucwords(str_replace('_', ' ', $field)) }}</h3>
                                <table class="w-full border border-gray-300 dark:border-gray-700 rounded-lg">
                                    <thead>
                                        <tr class="bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-white">
                                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-700">Key</th>
                                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-700">Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($deposit->$field as $key => $value)
                                            <tr class="text-gray-700 dark:text-gray-300">
                                                <td class="px-4 py-2 border border-gray-300 dark:border-gray-700">{{ ucfirst($key) }}</td>
                                                <td class="px-4 py-2 border border-gray-300 dark:border-gray-700">
                                                    @if(is_array($value) || is_object($value))
                                                        <pre class="text-sm">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif (!empty($deposit->$field))
                            <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg overflow-auto">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ ucwords(str_replace('_', ' ', $field)) }}</h3>
                                <p class="text-gray-600 dark:text-gray-300">{!! nl2br(e($deposit->$field)) !!}</p>
                            </div>
                        @endif
                    @endforeach
                </div>

            </div>

            <div id="user" class="tab-content hidden space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Name</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->firstName }} {{ $deposit->user->lastName }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Email</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->email }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Business Name</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->businessName }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Phone Number</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->phoneNumber }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Address</h3>
                        <p class="text-gray-600 dark:text-gray-300">
                            {{ $deposit->user->street }} {{ $deposit->user->houseNumber }}, 
                            {{ $deposit->user->city }}, {{ $deposit->user->state }}, 
                            {{ $deposit->user->zipCode }}, {{ $deposit->user->country }}
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Additional Info</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->additionalInfo }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">ID Number</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->idNumber }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">ID Type</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->idType }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Membership ID</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->membership_id }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Registration Country</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->user->registration_country }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Google 2FA Enabled</h3>
                        <p class="text-gray-600 dark:text-gray-300">
                            {{ $deposit->user->google2fa_enabled ? 'Yes' : 'No' }}
                        </p>
                    </div>
                </div>
            </div>
            

            <div id="gateway" class="tab-content hidden space-y-4">
                @if ($deposit->depositGateway)
                    <div class="bg-gray-50 dark:bg-slate-800 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($deposit->depositGateway->toArray() as $key => $value)
                                    <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ ucwords(str_replace('_', ' ', $key)) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                            {{ is_array($value) ? json_encode($value) : $value }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                        No gateway information available
                    </div>
                @endif
            </div>

            <div id="transactions" class="tab-content hidden space-y-4">
                @if ($deposit->transactions && $deposit->transactions->count() > 0)
                    @foreach ($deposit->transactions as $transaction)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($transaction->toArray() as $key => $value)
                                    <div class="space-y-1">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ ucwords(str_replace('_', ' ', $key)) }}
                                        </h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">
                                            {{ is_array($value) ? json_encode($value) : $value }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                        No transactions available
                    </div>
                @endif
            </div>
            <div id="statusChange" class="tab-content hidden space-y-4">
                <form action="{{ route('admin.deposits.update-status') }}" method="POST" class="max-w-md mx-auto bg-white shadow-md rounded-xl p-6 mt-6 border">
                    @csrf

                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Update Deposit Status</h2>

                    {{-- Show validation errors --}}
                    @if ($errors->any())
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded">
                            <ul class="text-sm list-disc pl-4">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Hidden input for deposit ID --}}
                    <input type="hidden" name="deposit_id" value="{{ $deposit->id }}">

                    {{-- Status dropdown --}}
                    <label for="deposit_status" class="block mb-2 text-sm font-medium text-gray-700">Select Status</label>
                    <select name="deposit_status" id="deposit_status" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Choose status --</option>
                        <option value="pending" {{ old('deposit_status', $deposit->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="failed" {{ old('deposit_status', $deposit->status) == 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="cancelled" {{ old('deposit_status', $deposit->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        <option value="success" {{ old('deposit_status', $deposit->status) == 'success' ? 'selected' : '' }}>Success</option>
                    </select>

                    {{-- Submit button --}}
                    <button type="submit" class="mt-4 w-full bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition">
                        Update Status
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.getAttribute('data-tab');

                // Remove active class from all buttons
                tabButtons.forEach(btn => btn.classList.remove('text-indigo-600', 'dark:text-indigo-400', 'border-indigo-500'));

                // Add active class to clicked button
                button.classList.add('text-indigo-600', 'dark:text-indigo-400', 'border-indigo-500');

                // Hide all tab contents
                tabContents.forEach(content => content.classList.add('hidden'));

                // Show the selected tab
                document.getElementById(targetTab).classList.remove('hidden');
            });
        });

        // Show the first tab by default
        tabButtons[0].click();
    });
</script>
@endsection
