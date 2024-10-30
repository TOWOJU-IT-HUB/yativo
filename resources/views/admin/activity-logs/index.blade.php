@extends('layouts.admin')

@section('content')
    <x-filament-card>
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Activity Logs</h1>

        <form action="{{ route('admin.activity-logs.index') }}" method="GET" class="mb-6 flex space-x-2">
            <input type="text" name="search" placeholder="Search logs"
                   value="{{ request('search') }}"
                   class="border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 px-4 py-2 rounded-lg">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                Search
            </button>
        </form>

        <table class="min-w-full bg-white dark:bg-gray-800 shadow-md rounded-lg">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="py-2 px-4 text-left text-sm font-medium text-gray-500 dark:text-gray-300">
                        <a href="{{ route('admin.activity-logs.index', ['sort' => 'log_name']) }}" class="hover:underline">
                            Log Name
                        </a>
                    </th>
                    <th class="py-2 px-4 text-left text-sm font-medium text-gray-500 dark:text-gray-300">
                        <a href="{{ route('admin.activity-logs.index', ['sort' => 'description']) }}" class="hover:underline">
                            Description
                        </a>
                    </th>
                    <th class="py-2 px-4 text-left text-sm font-medium text-gray-500 dark:text-gray-300">
                        <a href="{{ route('admin.activity-logs.index', ['sort' => 'subject.transaction_type']) }}" class="hover:underline">
                            Transaction Type
                        </a>
                    </th>
                    <th class="py-2 px-4 text-left text-sm font-medium text-gray-500 dark:text-gray-300">
                        <a href="{{ route('admin.activity-logs.index', ['sort' => 'causer.name']) }}" class="hover:underline">
                            Event/Log By
                        </a>
                    </th>
                    <th class="py-2 px-4 text-sm font-medium text-gray-500 dark:text-gray-300">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="py-2 px-4 text-sm text-gray-700 dark:text-gray-300">{{ $log->log_name }}</td>
                        <td class="py-2 px-4 text-sm text-gray-700 dark:text-gray-300">{{ $log->description }}</td>
                        <td class="py-2 px-4 text-sm text-gray-700 dark:text-gray-300">{{ optional($log->subject)->transaction_type }}</td>
                        <td class="py-2 px-4 text-sm text-gray-700 dark:text-gray-300">{{ optional($log->causer)->name }}</td>
                        <td class="py-2 px-4 text-sm">
                            <a href="{{ route('admin.activity-logs.show', $log) }}" class="text-blue-500 hover:underline">View</a>
                            <form action="{{ route('admin.activity-logs.destroy', $log) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </x-filament-card>
@endsection
