@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h2 class="text-slate-900 dark:text-white text-lg font-semibold">Edit Payment Method</h2>
    
    <form action="{{ route('admin.payin_methods.update', $payinMethod->id) }}" method="POST" class="bg-white dark:bg-boxdark p-6 shadow rounded">
        @csrf
        @method('PUT')
        @include('admin.payin_methods.form', ['method' => $payinMethod])
        <button type="submit" class="mt-4 px-4 py-2 bg-indigo-500 text-white rounded-md">Update Method</button>
    </form>
</div>
@endsection
