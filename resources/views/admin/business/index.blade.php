@extends('layouts.admin')
@section('title', 'Businesses')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6 dark:text-white">Businesses</h1>

    <div class="container">
        <div class="card">
            {{-- <div class="card-header">Manage Users</div> --}}
            <div class="card-body">
                {{ $dataTable->table() }}
            </div>
        </div>
    </div>
</div>
@endsection
 
@push('script')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush



{{--


@extends('layouts.admin')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6 dark:text-white">Businesses</h1>

    <!-- Filter Section -->
    <div class="mb-6">
        <form action="{{ route('admin.businesses.index') }}" method="GET" class="flex space-x-4 items-center">
            <div class="flex items-center">
                <label for="user_type" class="font-semibold mr-2 dark:text-white">Filter by User Type:</label>
                <select name="user_type" id="user_type" class="form-select border-gray-300 dark:border-gray-700 rounded-md shadow-sm dark:bg-gray-800 dark:text-white">
                    <option value="">All</option>
                    <option value="business" {{ request('user_type') === 'business' ? 'selected' : '' }}>Business</option>
                    <option value="individual" {{ request('user_type') === 'individual' ? 'selected' : '' }}>Individual</option>
                </select>
            </div>
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded shadow hover:bg-primary dark:bg-primary dark:hover:bg-primary">Filter</button>
        </form>
    </div>

    <!-- Business Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white dark:bg-gray-800 shadow rounded-lg">
            <thead>
                <tr>
                    <th class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Business Legal Name</th>
                    <th class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Operating Name</th>
                    <th class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Country</th>
                    <th class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Created At</th>
                    <th class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($businesses as $business)
                    <tr>
                        <td class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white">{{ $business->business_legal_name }}</td>
                        <td class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white">{{ $business->business_operating_name }}</td>
                        <td class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">{{ $business->incorporation_country }}</td>
                        <td class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">{{ $business->created_at->format('d M Y, h:i A') }}</td>
                        <td class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <a href="{{ route('admin.businesses.show', $business->id) }}" class="text-blue-500 hover:underline dark:text-blue-400">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-center text-sm text-gray-500 dark:text-gray-400">
                            No businesses found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination Links -->
    <div class="mt-6">
        {{ $businesses->links('pagination::tailwind') }}
    </div>
</div>
@endsection



--}}