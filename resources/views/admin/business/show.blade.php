@extends('layouts.admin')


@push('css')
    <style>
        .tabs {
            border-bottom: 2px solid #E5E7EB;
            display: flex;
        }

        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: border-color 0.3s ease;
        }

        .tab.active {
            border-color: #3B82F6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background-color: #FFFFFF;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            margin-top: 1.5rem;
        }

        .dark-mode .card {
            background-color: #1F2937;
            color: #E5E7EB;
        }
    </style>
@endpush


@section('content')
    <div class="bg-gray-100 dark:bg-boxdark p-6">

        <div class="container mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Business Details</h1>
                @if($user->kyc_status == null && $user->is_kyc_submitted == true)
                <a href="{{ route('admin.business.approve', $user->id) }}">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-md shadow-xl">
                        Approve Business
                    </button>
                </a>
                @endif
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <div id="tab-overview" class="tab active" onclick="toggleTab('overview')">Overview</div>
                <div id="tab-customers" class="tab" onclick="toggleTab('customers')">Customers</div>
                <div id="tab-virtual-accounts" class="tab" onclick="toggleTab('virtual-accounts')">Virtual Accounts</div>
                <div id="tab-virtual-cards" class="tab" onclick="toggleTab('virtual-cards')">Virtual Cards</div>
                <div id="tab-transactions" class="tab" onclick="toggleTab('transactions')">Transactions</div>
                <div id="tab-deposits" class="tab" onclick="toggleTab('deposits')">Deposits</div>
                <div id="tab-withdrawals" class="tab" onclick="toggleTab('withdrawals')">Withdrawals</div>
                <div id="tab-balance" class="tab" onclick="toggleTab('balance')">Wallet Balance</div>
            </div>

            <!-- Tab Content: Overview -->
            <div id="overview" class="tab-content active">
                <div>
                    <h2 class="text-xl font-semibold">Overview</h2>
                    
                    <!-- Business Legal Name -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Business Legal Name:</strong> {{ $business->business_legal_name }}
                    </p>
                    
                    <!-- Operating Name -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Operating Name:</strong> {{ $business->business_operating_name }}
                    </p>
                    
                    <!-- Country -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Country:</strong> {{ $business->incorporation_country }}
                    </p>
                    
                    <!-- Created At -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Created At:</strong> {{ \Carbon\Carbon::parse($business->created_at)->format('d M Y, h:i A') }}
                    </p>
                    
                    <!-- Business Description -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Business Description:</strong> {!! nl2br(e($business->business_description)) !!}
                    </p>
                    
                    <!-- Business Website -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Website:</strong> <a href="{{ $business->business_website }}" target="_blank">{{ $business->business_website }}</a>
                    </p>
                    
                    <!-- Entity Type -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Entity Type:</strong> {{ $business->entity_type }}
                    </p>
                    
                    <!-- Business Registration Number -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Business Registration Number:</strong> {{ $business->business_registration_number }}
                    </p>
                    
                    <!-- Business Tax ID -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Business Tax ID:</strong> {{ $business->business_tax_id }}
                    </p>
                    
                    <!-- Industry -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Industry:</strong> {{ $business->business_industry }}
                    </p>
                    
                    <!-- Sub Industry -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Sub Industry:</strong> {{ $business->business_sub_industry }}
                    </p>
                    
                    <!-- Account Purpose -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Account Purpose:</strong> {{ $business->account_purpose }}
                    </p>
                    
                    <!-- Plan of Use -->
                    <p class="text-gray-700 dark:text-gray-300">
                        <strong>Plan of Use:</strong> {{ $business->plan_of_use }}
                    </p>

                    <!-- Verification Data Section with Box Shadow -->
                    <div class="mt-6 p-4 bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                        <h3 class="text-lg font-semibold mb-4">Verification Information</h3>
                        <?= generateTableFromArray($business->business_verification_response) ?>
                        <!-- Verification Email -->

                    </div>
                </div>
            </div>


            <!-- Tab Content: Customers -->
            <div id="customers" class="tab-content">
                <div >
                    <h2 class="text-xl font-semibold">Customers</h2>
                    <table class="min-w-full bg-white dark:bg-boxdark">
                        <thead class="bg-gray-200 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-2 text-gray-500 dark:text-gray-300">Customer Name</th>
                                <th class="px-6 py-2 text-gray-500 dark:text-gray-300">Email</th>
                                <th class="px-6 py-2 text-gray-500 dark:text-gray-300">Phone</th>
                                <th class="px-6 py-2 text-gray-500 dark:text-gray-300">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer) : ?>
                            <tr>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?= $customer->customer_name ?></td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?= $customer->customer_email ?></td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?= $customer->customer_phone ?></td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300"><?= $customer->customer_status ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab Content: Virtual Accounts -->
            <div id="virtual-accounts" class="tab-content">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Virtual Accounts</h2>
                    @if ($virtualAccounts && count($virtualAccounts) > 0)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg shadow overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-900">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Account ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            User ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Currency
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Account Info
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Account Number
                                        </th>
                                        {{-- <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Customer ID
                                        </th> --}}
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Created At
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($virtualAccounts as $account)
                                        <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $account['account_id'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $account['user_id'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $account['currency'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                @if (isset($account['Account Info']) && is_array($account['Account Info']))
                                                    <ul class="list-disc list-inside space-y-1">
                                                        @foreach ($account['Account Info'] as $key => $value)
                                                            <li>
                                                                <strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    {{ $account['Account Info'] ?? 'N/A' }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $account['account_number'] ?? 'N/A' }}
                                            </td>
                                            {{-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $account['customer_id'] ?? 'N/A' }}
                                            </td> --}}
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ \Carbon\Carbon::parse($account['created_at'])->format('Y-m-d H:i:s') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                            No Virtual Accounts available
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Tab Content: Virtual Cards -->
            <div id="virtual-cards" class="tab-content">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Virtual Cards</h2>
                    @if ($virtualCards && count($virtualCards) > 0)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg shadow overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-900">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Card ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Card Number
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Card Name
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Card Brand
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Expiry Date
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Billing Address
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Balance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($virtualCards as $card)
                                        <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $card['id'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $card['cardNumber'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $card['cardName'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ ucwords($card['cardBrand'] ?? 'N/A') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ \Carbon\Carbon::parse($card['expiry'])->format('m/Y') ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                @if (isset($card['billingAddress']) && is_array($card['billingAddress']))
                                                    <div class="bg-gray-50 dark:bg-slate-900 rounded-lg p-2 shadow-inner">
                                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                            <tbody>
                                                                @foreach ($card['billingAddress'] as $key => $value)
                                                                    <tr>
                                                                        <td class="px-2 py-1 text-xs font-medium text-gray-900 dark:text-white">
                                                                            {{ ucwords(str_replace('_', ' ', $key)) }}
                                                                        </td>
                                                                        <td class="px-2 py-1 text-xs text-gray-600 dark:text-gray-300">
                                                                            {{ $value }}
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    {{ 'N/A' }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                ${{ number_format($card['balance'], 2) ?? 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                            No Virtual Cards Available
                        </div>
                    @endif
                </div>
            </div>
            

            <!-- Tab Content: Transactions -->
            <div id="transactions" class="tab-content">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Transactions</h2>
                    @if ($transactions && count($transactions) > 0)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg shadow overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-900">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Transaction ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Transaction Amount
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Memo
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Purpose
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($transactions as $transaction)
                                        <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $transaction['transaction_id'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                ${{ number_format($transaction['transaction_amount'], 2) ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ ucwords($transaction['transaction_status'] ?? 'N/A') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ ucwords($transaction['transaction_type'] ?? 'N/A') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $transaction['transaction_memo'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $transaction['transaction_purpose'] ?? 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                            No Transactions Available
                        </div>
                    @endif
                </div>
            </div>
            

            <!-- Tab Content: Deposits -->
            <div id="deposits" class="tab-content">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Deposits</h2>
                    @if ($deposits && count($deposits) > 0)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg shadow overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-900">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Deposit ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Currency
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Gateway
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Deposit Currency
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Receive Amount
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Created At
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Updated At
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Meta / Raw Data
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($deposits as $deposit)
                                        <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $deposit['id'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                ${{ number_format($deposit['amount'], 2) ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ ucwords($deposit['status'] ?? 'N/A') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $deposit['currency'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $deposit['gateway'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $deposit['deposit_currency'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                ${{ number_format($deposit['receive_amount'], 5) ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ \Carbon\Carbon::parse($deposit['created_at'])->format('Y-m-d H:i:s') ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ \Carbon\Carbon::parse($deposit['updated_at'])->format('Y-m-d H:i:s') ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                <div class="space-y-2">
                                                @if(is_array($deposit->meta) || is_object($deposit->meta))
                                                    <table class="w-full border border-gray-300 dark:border-gray-700 rounded-lg">
                                                        <thead>
                                                            <tr class="bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-white">
                                                                <th class="px-4 py-2 border border-gray-300 dark:border-gray-700">Key</th>
                                                                <th class="px-4 py-2 border border-gray-300 dark:border-gray-700">Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($deposit->meta as $key => $value)
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
                                                @else
                                                    <p class="text-gray-600 dark:text-gray-300">{!! $deposit->meta !!}</p>
                                                @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                            No Deposit Available
                        </div>
                    @endif
                </div>
            </div>
            

            <!-- Tab Content: Withdrawals -->
            <div id="withdrawals" class="tab-content">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Withdrawals</h2>
                    @if ($withdrawals && count($withdrawals) > 0)
                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg shadow overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-900">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Withdrawal ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Currency
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Payout ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Created At
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Updated At
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Raw Data
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Beneficiary ID
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($withdrawals as $withdrawal)
                                        <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $withdrawal['payout_id'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ number_format($withdrawal['amount'], 2) ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ ucwords($withdrawal['status'] ?? 'N/A') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ strtoupper($withdrawal['currency'] ?? 'N/A') }}
                                            </td>
                                            {{-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $withdrawal['payout_id'] ?? 'N/A' }}
                                            </td> --}}
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ \Carbon\Carbon::parse($withdrawal['created_at'])->format('Y-m-d H:i:s') ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ \Carbon\Carbon::parse($withdrawal['updated_at'])->format('Y-m-d H:i:s') ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                @if ($withdrawal['raw_data'])
                                                    <div class="bg-gray-100 dark:bg-slate-700 p-2 rounded-lg text-xs">
                                                        <pre>{{ json_encode($withdrawal['raw_data'], JSON_PRETTY_PRINT) }}</pre>
                                                    </div>
                                                @else
                                                    <span>No raw data</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                {{ $withdrawal['beneficiary_id'] ?? 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-600 dark:text-gray-400">
                            No Withdrawals Available
                        </div>
                    @endif
                </div>
            </div>
            

            <!-- Tab Content: Overview -->
            <div id="balance" class="tab-content">
                <h2 class="mb-4">Wallet Balances</h2>
                <div class="container mx-auto p-6">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-6">
                        Wallet Balances for {{ $user->name }}
                    </h2>

                    <div class="overflow-x-auto bg-white shadow-md rounded-lg">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-800 text-white uppercase text-sm leading-normal">
                                    <th class="py-3 px-6">#</th>
                                    <th class="py-3 px-6">Wallet Slug</th>
                                    <th class="py-3 px-6">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 text-sm font-light">
                                @forelse($wallets as $index => $wallet)
                                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                                        <td class="py-3 px-6">{{ $index + 1 }}</td>
                                        <td class="py-3 px-6 font-medium">{{ $wallet->slug }}</td>
                                        <td class="py-3 px-6 font-semibold text-green-600">
                                            ${{ number_format($wallet->balanceFloat, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-4 px-6 text-center text-gray-500">
                                            No wallets found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('script')
    <script>
        function toggleTab(tab) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));

            // Show the selected tab content
            document.getElementById(tab).classList.add('active');
            document.getElementById(`tab-${tab}`).classList.add('active');
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark-mode');
        }
    </script>
@endpush
