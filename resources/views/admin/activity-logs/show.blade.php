@extends('layouts.admin')

@section('content')
    <x-filament-card>
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Activity Log Details</h1>

        <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Log Name:</strong> {{ $activityLog->log_name }}</p>
        <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Description:</strong> {{ $activityLog->description }}</p>
        <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Transaction Type:</strong> {{ optional($activityLog->subject)->transaction_type }}</p>
        <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Event/Log By:</strong> {{ optional($activityLog->causer)->name }}</p>

        <a href="{{ route('admin.activity-logs.index') }}" class="mt-4 bg-primary text-white px-4 py-2 rounded-lg">Back to list</a>
    </x-filament-card>
@endsection
