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
    <div class="bg-gray-100 dark:bg-gray-900 p-6">

        <div class="container mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Business Details</h1>
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
            </div>

            <!-- Tab Content: Overview -->
            <div id="overview" class="tab-content active">
                <div class="card">
                    <h2 class="text-xl font-semibold">Overview</h2>
                    <p class="text-gray-700 dark:text-gray-300">Business Legal Name: <?= $business->business_legal_name ?>
                    </p>
                    <p class="text-gray-700 dark:text-gray-300">Operating Name: <?= $business->business_operating_name ?>
                    </p>
                    <p class="text-gray-700 dark:text-gray-300">Country: <?= $business->incorporation_country ?></p>
                    <p class="text-gray-700 dark:text-gray-300">Created At:
                        <?= date('d M Y, h:i A', strtotime($business->created_at)) ?></p>
                </div>
            </div>

            <!-- Tab Content: Customers -->
            <div id="customers" class="tab-content">
                <div class="card">
                    <h2 class="text-xl font-semibold">Customers</h2>
                    <table class="min-w-full bg-white dark:bg-gray-800">
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
                <div class="card">
                    <h2 class="text-xl font-semibold">Virtual Accounts</h2>
                    <p class="text-gray-700 dark:text-gray-300">List of Virtual Accounts will be shown here.</p>
                </div>
            </div>

            <!-- Tab Content: Virtual Cards -->
            <div id="virtual-cards" class="tab-content">
                <div class="card">
                    <h2 class="text-xl font-semibold">Virtual Cards</h2>
                    <p class="text-gray-700 dark:text-gray-300">List of Virtual Cards will be shown here.</p>
                </div>
            </div>

            <!-- Tab Content: Transactions -->
            <div id="transactions" class="tab-content">
                <div class="card">
                    <h2 class="text-xl font-semibold">Transactions</h2>
                    <p class="text-gray-700 dark:text-gray-300">Transaction history will be shown here.</p>
                </div>
            </div>

            <!-- Tab Content: Deposits -->
            <div id="deposits" class="tab-content">
                <div class="card">
                    <h2 class="text-xl font-semibold">Deposits</h2>
                    <p class="text-gray-700 dark:text-gray-300">Deposit history will be shown here.</p>
                </div>
            </div>

            <!-- Tab Content: Withdrawals -->
            <div id="withdrawals" class="tab-content">
                <div class="card">
                    <h2 class="text-xl font-semibold">Withdrawals</h2>
                    <p class="text-gray-700 dark:text-gray-300">Withdrawal history will be shown here.</p>
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
