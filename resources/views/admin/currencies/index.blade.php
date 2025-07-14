@extends('layouts.admin')

@section('content')
    <div name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Currencies</h2>
    </div>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <a href="{{ route('admin.currencies.create') }}" class="bg-blue-500 hover:bg-primary text-white font-bold py-2 px-4 rounded mb-4 inline-block">Add New Currency</a>
                    @if(session('success'))
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 my-4" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <table class="min-w-full bg-white dark:bg-gray-700 mt-4 rounded-lg overflow-hidden">
                        <thead class="bg-gray-200 dark:bg-gray-900 text-left">
                            <tr>
                                <th class="px-4 py-2 text-gray-800 dark:text-gray-200">Name</th>
                                <th class="px-4 py-2 text-gray-800 dark:text-gray-200">Country</th>
                                <th class="px-4 py-2 text-gray-800 dark:text-gray-200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($currencies as $currency)
                                <tr class="border-b dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $currency['currency_name'] }}</td>
                                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $currency['currency_country'] }}</td>
                                    <td class="px-4 py-2 flex space-x-2">
                                        <a href="{{ route('admin.currencies.edit', $currency['id']) }}" class="text-blue-500 hover:text-blue-700">Edit</a>
                                        <form action="{{ route('admin.currencies.destroy', $currency['id']) }}" method="POST" onsubmit="return confirm('Are you sure?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-center text-gray-500 dark:text-gray-400">No currencies found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection