@extends('layouts.admin')

@section('content')
    <div name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Create Currency</h2>
    </div>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form action="{{ route('admin.currencies.store') }}" method="POST">
                        @csrf
                        @include('admin.currencies._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection