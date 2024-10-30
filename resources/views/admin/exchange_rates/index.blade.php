@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-4">Exchange Rates</h1>

    <div class="bg-white dark:bg-slate-800 rounded-lg px-6 py-8 ring-1 ring-slate-900/5 shadow-xl">
        <a href="{{ route('admin.exchange_rates.create') }}" class="text-blue-500 mb-4 inline-block">Create New Exchange Rate</a>
        <table class="min-w-full bg-white dark:bg-slate-800">
            <thead>
                <tr>
                    <th class="text-left text-slate-900 dark:text-white">Gateway ID</th>
                    <th class="text-left text-slate-900 dark:text-white">Rate Type</th>
                    <th class="text-left text-slate-900 dark:text-white">Float Percentage</th>
                    <th class="text-left text-slate-900 dark:text-white">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($exchangeRates as $exchangeRate)
                <tr>
                    <td class="text-slate-500 dark:text-slate-400">{{ $exchangeRate->gateway_id }}</td>
                    <td class="text-slate-500 dark:text-slate-400">{{ $exchangeRate->rate_type }}</td>
                    <td class="text-slate-500 dark:text-slate-400">{{ $exchangeRate->float_percentage }}</td>
                    <td class="text-slate-500 dark:text-slate-400">
                        <a href="{{ route('admin.exchange_rates.edit', $exchangeRate->id) }}" class="text-blue-500">Edit</a>
                        <form action="{{ route('admin.exchange_rates.destroy', $exchangeRate->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
