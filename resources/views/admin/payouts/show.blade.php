@extends('layouts.admin')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Payout Details</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">ID: {{ $payout->id }}</p>
        </div>

        <div class="bg-white dark:bg-boxdark rounded-lg shadow-lg overflow-hidden">
            <!-- Tabs Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex -mb-px">
                    <button data-tab="payout"
                        class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                        payout Info
                    </button>
                    <button data-tab="user"
                        class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                        User Info
                    </button>
                    @if ($payout->customer)
                    <button data-tab="customer"
                        class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                        Customer Info
                    </button>
                    @endif
                    <button data-tab="gateway"
                        class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                        Gateway Info
                    </button>
                    <button data-tab="transactions"
                        class="tab-button px-6 py-3 border-b-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                        Transactions
                    </button>
                </nav>
            </div>

            <!-- Tab Contents -->
            <div class="p-6">
                <div id="payout" class="tab-content hidden space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Status</h3>
                            <span
                                class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                            @if ($payout->status === 'pending') bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200
                            @elseif($payout->status === 'completed') bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200
                            @else bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200 @endif">
                                {{ ucfirst($payout->status) }}
                            </span>
                        </div>
                        @if (!empty($payout->raw_data))
                            <div class="bg-gray-50 dark:bg-slate-800 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($payout->raw_data as $key => $value)
                                            <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ ucwords(str_replace('_', ' ', $key)) }}
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ is_array($value) ? json_encode($value) : $value }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if ($payout->beneficiary)
                            <div class="bg-gray-50 dark:bg-slate-800 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($payout->beneficiary?->payment_data as $key => $value)
                                            <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ ucwords(str_replace('_', ' ', $key)) }}
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ is_array($value) ? json_encode($value) : $value }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    
                    <div class="flex justify-center gap-4 my-3">
                        @if ($payout->status === 'pending')
                            <div x-data="{ modalOpen: false }">
                                <button @click="modalOpen = true"
                                    class="rounded-md bg-primary px-9 py-3 font-medium text-white">
                                    Reject Payout
                                </button>
                                <div x-show="modalOpen" x-transition=""
                                    class="fixed left-0 top-0 z-999999 flex h-full min-h-screen w-full items-center justify-center bg-black/90 px-4 py-5">
                                    <div @click.outside="modalOpen = false"
                                        class="w-full max-w-142.5 rounded-lg bg-white px-8 py-12 text-center dark:bg-boxdark md:px-17.5 md:py-15">
                                        <span class="mx-auto inline-block">
                                            <svg width="60" height="60" viewBox="0 0 60 60" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <rect opacity="0.1" width="60" height="60" rx="30"
                                                    fill="#DC2626">
                                                </rect>
                                                <path
                                                    d="M30 27.2498V29.9998V27.2498ZM30 35.4999H30.0134H30ZM20.6914 41H39.3086C41.3778 41 42.6704 38.7078 41.6358 36.8749L32.3272 20.3747C31.2926 18.5418 28.7074 18.5418 27.6728 20.3747L18.3642 36.8749C17.3296 38.7078 18.6222 41 20.6914 41Z"
                                                    stroke="#DC2626" stroke-width="2.2" stroke-linecap="round"
                                                    stroke-linejoin="round"></path>
                                            </svg>
                                        </span>
                                        <h3 class="mt-5.5 pb-2 text-xl font-bold text-black dark:text-white sm:text-2xl">
                                            Reject Payout
                                        </h3>
                                        <p class="mb-10 font-medium">
                                            Are you sure you want to reject this payout? Please select a reason.
                                        </p>
                                        <form action="{{ route('admin.payouts.reject', $payout->id) }}" method="POST">
                                            @csrf
                                            <div class="mb-4">
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason
                                                    for
                                                    Rejection</label>
                                                <select name="reason"
                                                    class="relative z-20 w-full appearance-none rounded border border-stroke bg-transparent py-3 pl-5 pr-12 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input">
                                                    <option value="insufficient_funds">Insufficient Funds</option>
                                                    <option value="invalid_account">Invalid Account Details</option>
                                                    <option value="suspicious_activity">Suspicious Activity</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div class="mb-4">
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Additional
                                                    Comments</label>
                                                <textarea name="comments" rows="3"
                                                    class="relative z-20 w-full appearance-none rounded border border-stroke bg-transparent py-3 pl-5 pr-12 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input"></textarea>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <button @click="modalOpen = false" type="button"
                                                    class="block w-full rounded border border-stroke bg-gray p-3 text-center font-medium text-black transition hover:border-meta-1 hover:bg-meta-1 hover:text-white dark:border-strokedark dark:bg-meta-4 dark:text-white dark:hover:border-meta-1 dark:hover:bg-meta-1">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="px-4 py-2 text-sm font-medium text-white bg-danger rounded-md hover:bg-red-600">
                                                    Reject
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>

                            <div x-data="{ modalOpen: false }">
                                <button @click="modalOpen = true"
                                    class="rounded-md bg-primary px-9 py-3 font-medium text-white">
                                    Accept Payout
                                </button>
                                <div x-show="modalOpen" x-transition=""
                                    class="fixed left-0 top-0 z-999999 flex h-full min-h-screen w-full items-center justify-center bg-black/90 px-4 py-5">
                                    <div @click.outside="modalOpen = false"
                                        class="w-full max-w-142.5 rounded-lg bg-white px-8 py-12 text-center dark:bg-boxdark md:px-17.5 md:py-15">
                                        <span class="mx-auto inline-block">
                                            <svg width="60" height="60" viewBox="0 0 60 60" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <rect opacity="0.1" width="60" height="60" rx="30"
                                                    fill="#DC2626">
                                                </rect>
                                                <path
                                                    d="M30 27.2498V29.9998V27.2498ZM30 35.4999H30.0134H30ZM20.6914 41H39.3086C41.3778 41 42.6704 38.7078 41.6358 36.8749L32.3272 20.3747C31.2926 18.5418 28.7074 18.5418 27.6728 20.3747L18.3642 36.8749C17.3296 38.7078 18.6222 41 20.6914 41Z"
                                                    stroke="#DC2626" stroke-width="2.2" stroke-linecap="round"
                                                    stroke-linejoin="round"></path>
                                            </svg>
                                        </span>
                                        <h3 class="mt-5.5 pb-2 text-xl font-bold text-black dark:text-white sm:text-2xl">
                                            Reject Payout
                                        </h3>
                                        <p class="mb-10 font-medium">
                                            Are you sure you want to reject this payout? Please select a reason.
                                        </p>
                                        <form action="{{ route('admin.payouts.reject', $payout->id) }}" method="POST">
                                            @csrf
                                            <div class="mb-4">
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason
                                                    for
                                                    Rejection</label>
                                                <select name="reason"
                                                    class="relative z-20 w-full appearance-none rounded border border-stroke bg-transparent py-3 pl-5 pr-12 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input">
                                                    <option value="insufficient_funds">Insufficient Funds</option>
                                                    <option value="invalid_account">Invalid Account Details</option>
                                                    <option value="suspicious_activity">Suspicious Activity</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div class="mb-4">
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Additional
                                                    Comments</label>
                                                <textarea name="comments" rows="3"
                                                    class="relative z-20 w-full appearance-none rounded border border-stroke bg-transparent py-3 pl-5 pr-12 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input"></textarea>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <button @click="modalOpen = false" type="button"
                                                    class="block w-full rounded border border-stroke bg-gray p-3 text-center font-medium text-black transition hover:border-meta-1 hover:bg-meta-1 hover:text-white dark:border-strokedark dark:bg-meta-4 dark:text-white dark:hover:border-meta-1 dark:hover:bg-meta-1">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="px-4 py-2 text-sm font-medium text-white bg-danger rounded-md hover:bg-red-600">
                                                    Reject
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>

                            <div x-data="{ modalOpen: false }">
                                <button @click="modalOpen = true"
                                    class="rounded-md bg-primary px-9 py-3 font-medium text-white">
                                    Process Automatically via API
                                </button>
                                <div x-show="modalOpen" x-transition=""
                                    class="fixed left-0 top-0 z-999999 flex h-full min-h-screen w-full items-center justify-center bg-black/90 px-4 py-5">
                                    <div @click.outside="modalOpen = false"
                                        class="w-full max-w-142.5 rounded-lg bg-white px-8 py-12 text-center dark:bg-boxdark md:px-17.5 md:py-15">
                                        <span class="mx-auto inline-block">
                                            <svg width="60" height="60" viewBox="0 0 60 60" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <rect opacity="0.1" width="60" height="60" rx="30"
                                                    fill="#DC2626">
                                                </rect>
                                                <path
                                                    d="M30 27.2498V29.9998V27.2498ZM30 35.4999H30.0134H30ZM20.6914 41H39.3086C41.3778 41 42.6704 38.7078 41.6358 36.8749L32.3272 20.3747C31.2926 18.5418 28.7074 18.5418 27.6728 20.3747L18.3642 36.8749C17.3296 38.7078 18.6222 41 20.6914 41Z"
                                                    stroke="#DC2626" stroke-width="2.2" stroke-linecap="round"
                                                    stroke-linejoin="round"></path>
                                            </svg>
                                        </span>

                                        <h3 class="mt-5.5 pb-2 text-xl font-bold text-black dark:text-white sm:text-2xl">
                                            Accept Payout
                                        </h3>
                                        <p class="mb-10 font-medium">
                                            Are you sure you want to accept this payout?
                                        </p>
                                        <form action="{{ route('admin.payouts.accept', ['id' => $payout->id]) }}" method="POST">
                                            @csrf
                                            <div class="flex justify-end">
                                                <button type="button" @click="modalOpen = false"
                                                    class="mr-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                    class="px-4 py-2 text-sm font-medium text-white bg-green-500 rounded-md hover:bg-green-600">
                                                    Accept
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        @endif
                    </div>


                </div>

                <div id="user" class="tab-content hidden space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Name</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->firstName }}
                                {{ $payout->user->lastName }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Email</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->email }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Business Name</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->businessName }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Phone Number</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->phoneNumber }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Address</h3>
                            <p class="text-gray-600 dark:text-gray-300">
                                {{ $payout->user->street }} {{ $payout->user->houseNumber }},
                                {{ $payout->user->city }}, {{ $payout->user->state }},
                                {{ $payout->user->zipCode }}, {{ $payout->user->country }}
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Additional Info</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->additionalInfo }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">ID Number</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->idNumber }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">ID Type</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->idType }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Membership ID</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->membership_id }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Registration Country</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ $payout->user->registration_country }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Google 2FA Enabled</h3>
                            <p class="text-gray-600 dark:text-gray-300">
                                {{ $payout->user->google2fa_enabled ? 'Yes' : 'No' }}
                            </p>
                        </div>
                    </div>
                </div>


                <div id="gateway" class="tab-content hidden space-y-4">
                    @if ($payout->payoutGateway)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($payout->payoutGateway->toArray() as $key => $value)
                                        <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {{ ucwords(str_replace('_', ' ', $key)) }}
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
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
                    @if ($payout->transactions && $payout->transactions->count() > 0)
                        @foreach ($payout->transactions as $transaction)
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

                <div id="customer" class="tab-content hidden space-y-4">
                    @if ($payout->customer)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($payout->customer->toArray() as $key => $value)
                                        <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {{ ucwords(str_replace('_', ' ', $key)) }}
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                @if (is_array($value))
                                                    @include('components.recursive-array-table', [
                                                        'array' => $value,
                                                    ])
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                            No customer information available
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetTab = button.getAttribute('data-tab');

                    // Remove active class from all buttons
                    tabButtons.forEach(btn => btn.classList.remove('text-indigo-600',
                        'dark:text-indigo-400', 'border-indigo-500'));

                    // Add active class to clicked button
                    button.classList.add('text-indigo-600', 'dark:text-indigo-400',
                        'border-indigo-500');

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
