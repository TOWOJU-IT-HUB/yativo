@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-4">Deposits</h1>

    <div class="bg-white dark:bg-slate-800 rounded-lg px-6 py-8 ring-1 ring-slate-900/5 shadow-xl">
        <table class="min-w-full bg-white dark:bg-slate-800">
            <thead>
                <tr>
                    <th class="text-left text-slate-900 dark:text-white">User Name</th>
                    <th class="text-left text-slate-900 dark:text-white">Deposit Gateway</th>
                    <th class="text-left text-slate-900 dark:text-white">Status</th>
                    <th class="text-left text-slate-900 dark:text-white">Currency</th>
                    <th class="text-left text-slate-900 dark:text-white">Amount</th>
                    <th class="text-left text-slate-900 dark:text-white">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deposits as $deposit)
                <tr>
                    <td class="text-slate-500 dark:text-slate-400">{{ $deposit->user?->name }}</td>
                    <td class="text-slate-500 dark:text-slate-400">{{ $deposit->depositGateway?->method_name }}</td>
                    <td class="text-slate-500 dark:text-slate-400">
                        @php
                        $status = $deposit->transactions->transaction_status;
                        $color = match ($status) {
                            'pending' => 'warning',
                            'completed' => 'success',
                            'cancelled', 'failed', 'expired' => 'danger',
                            'processing' => 'primary',
                            default => 'secondary',
                        };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-{{ $color }}-100 text-{{ $color }}-800">{{ $status }}</span>
                    </td>
                    <td class="text-slate-500 dark:text-slate-400">{{ $deposit?->currency }}</td>
                    <td class="text-slate-500 dark:text-slate-400">{{ $deposit?->amount }}</td>
                    <td class="text-slate-500 dark:text-slate-400">
                        <a href="{{ route('admin.deposits.show', $deposit->id) }}" class="text-blue-500">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
