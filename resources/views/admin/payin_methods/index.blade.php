@extends('layouts.admin')

@section('content')
    <div class="bg-white dark:bg-slate-800 rounded-lg p-6 ring-1 ring-slate-900/5 shadow-xl">

        <div class="flex justify-between items-center m-3">
            <h2 class="text-slate-900 dark:text-white text-lg font-semibold">Payin Gateways</h2>
            <a href="{{ route('admin.payin_methods.create') }}"
                class="mt-4 inline-block px-4 py-2 bg-indigo-500 text-white rounded-md">Add New Method</a>
        </div>

        @if (session('success'))
            <div class="mt-4 text-green-600 dark:text-green-400">{{ session('success') }}</div>
        @endif

        <table class="min-w-full mt-4">
            <thead>
                <tr>
                    <th class="text-left">Method Name</th>
                    <th class="text-left">Gateway</th>
                    <th class="text-left">Country</th>
                    <th class="text-left">Currency</th>
                    <th class="text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payinMethods as $method)
                    <tr class="border-b">
                        <td class="py-2">{{ $method->method_name }}</td>
                        <td class="py-2">{{ $method->gateway }}</td>
                        <td class="py-2">{{ $method->country }}</td>
                        <td class="py-2">{{ $method->currency }}</td>
                        <td class="py-2">
                            <a href="{{ route('admin.payin_methods.edit', $method->id) }}" class="text-blue-500">Edit</a>
                            <form action="{{ route('admin.payin_methods.destroy', $method->id) }}" method="POST"
                                style="display:inline;">
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
@endsection
