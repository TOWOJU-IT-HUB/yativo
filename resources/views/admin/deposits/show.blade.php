@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-4">Deposit Details</h1>

    <div class="bg-white dark:bg-slate-800 rounded-lg px-6 py-8 ring-1 ring-slate-900/5 shadow-xl">
        <h3 class="text-slate-900 dark:text-white text-base font-medium tracking-tight">Deposit by {{ $deposit->user->name }}</h3>
        <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm">Gateway: {{ $deposit->depositGateway->method_name }}</p>
        <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm">Status: 
            @php
            $status = $deposit->transaction->transaction_status;
            $color = match ($status) {
                'pending' => 'warning',
                'completed' => 'success',
                'cancelled', 'failed', 'expired' => 'danger',
                'processing' => 'primary',
                default => 'secondary',
            };
            @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-{{ $color }}-100 text-{{ $color }}-800">{{ $status }}</span>
        </p>
        <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm">Currency: {{ $deposit->currency }}</p>
        <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm">Amount: {{ $deposit->amount }}</p>
    </div>
</div>
@endsection
