@extends('admin.layout')

@section('content')
<div class="bg-white dark:bg-slate-800 rounded-lg p-6 ring-1 ring-slate-900/5 shadow-xl">
    <h2 class="text-slate-900 dark:text-white text-lg font-semibold">Payment Method Details</h2>
    <p><strong>Method Name:</strong> {{ $payinMethod->method_name }}</p>
    <p><strong>Gateway:</strong> {{ $payinMethod->gateway }}</p>
    <p><strong>Country:</strong> {{ $payinMethod->country }}</p>
    <p><strong>Currency:</strong> {{ $payinMethod->currency }}</p>
    <!-- Add more fields as necessary -->

    <a href="{{ route('admin.payin_methods.index') }}" class="mt-4 inline-block px-4 py-2 bg-indigo-500 text-white rounded-md">Back to List</a>
</div>
@endsection
