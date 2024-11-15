@extends('layouts.admin')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6 dark:text-white">Businesses</h1>

    <!-- Filter Section -->
    <div class="mb-6 w-full ">
        <form action="{{ route('admin.businesses.index') }}" method="GET" class="flex space-x-4 items-center">
            <div class="flex items-center">
                <label for="user_type" class="font-semibold mr-2 w-full dark:text-white">Filter by User Type:</label>
                <select name="user_type" id="user_type" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
                    <option value="">All</option>
                    <option value="business" {{ request('user_type') === 'business' ? 'selected' : '' }}>Business</option>
                    <option value="individual" {{ request('user_type') === 'individual' ? 'selected' : '' }}>Individual</option>
                </select>
            </div>
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded shadow hover:bg-primary dark:bg-boxdark dark:hover:bg-boxdark">Filter</button>
        </form>
    </div>

    <!-- Business Table -->
    <div class="overflow-x-auto">
        <table class="w-full bg-white dark:bg-boxdark shadow rounded-lg">
            <thead>
                <tr>
                    <th class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-white">Business Legal Name</th>
                    <th class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-white">Operating Name</th>
                    <th class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-white">Country</th>
                    <th class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-white">Created At</th>
                    <th class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-left text-sm font-medium text-gray-500 dark:text-white">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($businesses as $business)
                    <tr>
                        <td class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white">{{ $business->business_legal_name }}</td>
                        <td class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white">{{ $business->business_operating_name }}</td>
                        <td class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">{{ $business->incorporation_country }}</td>
                        <td class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">{{ $business->created_at->format('d M Y, h:i A') }}</td>
                        <td class="px-6 py-4 border-b hover:bg-gray-200 dark:border-gray-700">
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
