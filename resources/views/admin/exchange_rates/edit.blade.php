@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-4">Edit Exchange Rate</h1>

    <form action="{{ route('admin.exchange_rates.update', $exchangeRate->id) }}" method="POST" class="bg-white dark:bg-boxdark rounded-lg p-6 ring-1 ring-slate-900/5 shadow-xl">
        @csrf
        @method('PUT')
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="rate_type">Rate Type</label>
            <select name="rate_type" id="rate_type"
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" required>
                <option value="percentage" {{ $exchangeRate->rate_type == 'percentage' ? 'selected' : '' }}>Percentage</option>
                <option value="fixed" {{ $exchangeRate->rate_type == 'fixed' ? 'selected' : '' }}>Fixed</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="gateway">Gateway</label>
            <input type="text" name="gateway" id="gateway"
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" value="{{ $exchangeRate->gateway }}" required>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="float_percentage">Float Percentage</label>
            <input type="number" name="float_percentage" id="float_percentage"
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" value="{{ $exchangeRate->float_percentage }}" required>
        </div>

        <button type="submit" class="bg-primary hover:bg-purple-800 text-white px-4 py-2 rounded-md">Update</button>
    </form>
</div>
@endsection
