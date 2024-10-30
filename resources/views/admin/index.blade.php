@extends('layouts.app')

@section('content')

<div class="mx-auto py-8 px-4 sm:px-6 lg:px-8 bg-white dark:bg-slate-800 rounded-lg shadow-xl ring-1 ring-slate-900/5">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white mb-4 sm:mb-0">Admins</h1>
        <a href="{{ route('admin.create') }}" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg transition duration-300 ease-in-out transform hover:scale-105">
            Create Admin
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 dark:bg-green-800 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-x-auto w-full">
        <table class="w-full bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg shadow-md">
            <thead class="bg-slate-50 dark:bg-slate-600">
                <tr>
                    <th class="py-3 px-4 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">Name</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">Email</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">Last Login</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                @foreach($admins as $admin)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-600 transition duration-150 ease-in-out">
                        <td class="py-4 px-4 text-sm text-slate-700 dark:text-slate-300">{{ $admin->name }}</td>
                        <td class="py-4 px-4 text-sm text-slate-700 dark:text-slate-300">{{ $admin->email }}</td>
                        <td class="py-4 px-4 text-sm text-slate-700 dark:text-slate-300">{{ optional($admin->last_login_at)->format('Y-m-d H:i') }}</td>
                        <td class="py-4 px-4 text-sm">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.edit', $admin) }}" class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded transition duration-300 ease-in-out">Edit</a>
                                <form action="{{ route('admin.destroy', $admin) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded transition duration-300 ease-in-out">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $admins->links() }}
    </div>
</div>
@endsection