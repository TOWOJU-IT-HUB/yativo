@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h2 class="text-slate-900 dark:text-white text-lg font-semibold">Add New Payment Method</h2>
    
    

    <form action="{{ route('admin.payin_methods.store') }}" method="POST"  class="bg-white dark:bg-boxdark p-6 shadow rounded">
        @csrf
        @include('admin.payin_methods.form')
        <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-focus text-white rounded-lg transition duration-300 ease-in-out">Create Method</button>
    </form>
</div>
@endsection
