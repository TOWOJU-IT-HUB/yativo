@extends('layouts.admin')

@section('content')
    <div class="container mx-auto p-6">
    <div class="rounded-sm border border-stroke bg-white px-5 pb-2.5 pt-6 shadow-default dark:border-strokedark dark:bg-boxdark sm:px-7.5 xl:pb-1">
        <div class="max-w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-2 text-left dark:bg-meta-4">
                        <th class="min-w-[220px] px-4 py-4 font-medium text-black dark:text-white xl:pl-11">Key</th>
                        <th class="min-w-[150px] px-4 py-4 font-medium text-black dark:text-white">Value</th>
                        <th class="min-w-[120px] px-4 py-4 font-medium text-black dark:text-white">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($settings as $k => $setting)
                        <tr>
                            <td class="border-b border-[#eee] px-4 py-5 pl-9 dark:border-strokedark xl:pl-11">
                                <h5 class="font-medium text-black dark:text-white">{{ str_replace('_', ' ', ucfirst($k)) }}</h5>
                                <p class="text-sm">{{ str_replace('_', ' ', ucfirst($setting)) }}</p>
                            </td>
                            <td class="border-b border-[#eee] px-4 py-5 dark:border-strokedark">
                                <p class="text-black dark:text-white">{{ $setting }}</p>
                            </td>
                            <td class="border-b border-[#eee] px-4 py-5 dark:border-strokedark">
                                <div class="flex items-center space-x-3.5">
                                    <button class="hover:text-primary" data-bs-toggle="modal" data-bs-target="#viewModal{{ $k }}">
                                        <svg class="fill-current" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <!-- View Icon SVG here -->
                                        </svg>
                                    </button>
                                    <button class="hover:text-primary" data-bs-toggle="modal" data-bs-target="#editModal{{ $k }}">
                                        <svg class="fill-current" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <!-- Edit Icon SVG here -->
                                        </svg>
                                    </button>
                                    <form action="{{ route('admin.settings.destroy', $setting) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this setting?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="hover:text-primary">
                                            <svg class="fill-current" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <!-- Delete Icon SVG here -->
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- View Modal -->
                        <div class="modal fade" id="viewModal{{ $k }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content bg-white shadow-lg rounded-md">
                                    <div class="modal-header border-b p-4">
                                        <h5 class="text-lg font-semibold">Setting Details</h5>
                                        <button type="button" class="text-gray-500" data-bs-dismiss="modal">✖</button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <p><strong>Key:</strong> {{ str_replace('_', ' ', ucfirst($k)) }}</p>
                                        <p><strong>Value:</strong> {{ $setting }}</p>
                                    </div>
                                    <div class="modal-footer p-4 border-t">
                                        <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600" data-bs-toggle="modal" data-bs-target="#editModal{{ $k }}">Edit</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal{{ $k }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content bg-white shadow-lg rounded-md">
                                    <div class="modal-header border-b p-4">
                                        <h5 class="text-lg font-semibold">Edit Setting - {{ $k }}</h5>
                                        <button type="button" class="text-gray-500" data-bs-dismiss="modal">✖</button>
                                    </div>
                                    <form action="{{ route('admin.settings.update', $k) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body p-4">
                                            <div class="mb-4">
                                                <label for="setting_value" class="block text-sm font-medium text-gray-600">Value</label>
                                                <input type="text" id="setting_value" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500" name="setting_value" value="{{ $setting }}">
                                            </div>
                                        </div>
                                        <div class="modal-footer p-4 border-t">
                                            <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </div>
@endsection
