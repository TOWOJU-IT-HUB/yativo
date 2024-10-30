@extends('admin.layout')

@section('content')
<div class="bg-white dark:bg-slate-800 rounded-lg p-6 ring-1 ring-slate-900/5 shadow-xl">
    <h2 class="text-slate-900 dark:text-white text-lg font-semibold">Edit Payment Method</h2>
    
    <form action="{{ route('admin.payin_methods.update', $payinMethod->id) }}" method="POST" class="mt-4">
        @csrf
        @method('PUT')
        @include('admin.payin_methods.form', ['method' => $payinMethod])
        <button type="submit" class="mt-4 px-4 py-2 bg-indigo-500 text-white rounded-md">Update Method</button>
    </form>
</div>
@endsection
