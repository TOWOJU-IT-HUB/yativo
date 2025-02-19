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
            </nav>
        </div>

        <!-- Tab Contents -->
        <div class="p-6">
            <div id="deposit" class="tab-content hidden space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Amount</h3>
                        <p class="text-gray-600 dark:text-gray-300">{{ $deposit->amount }} {{ $deposit->currency }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Status</h3>
                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                            @if ($deposit->status === 'pending') bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200
                            @elseif($deposit->status === 'completed') bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200
                            @else bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200 @endif">
                            {{ ucfirst($deposit->status) }}
                        </span>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Meta </h3>
                        <p class="text-gray-600 dark:text-gray-300">{!! $deposit->meta !!}</p>
                    </div>
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
