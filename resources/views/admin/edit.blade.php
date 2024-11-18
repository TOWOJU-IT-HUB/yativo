@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-semibold text-gray-800">Edit Admin</h1>

    @if(isset($errors))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.update', $admin) }}" method="POST" class="bg-white dark:bg-boxdark shadow-md rounded-lg p-6">
        @csrf
        @method('PUT')
    
        <!-- Name -->
        <div class="mb-4">
            <label for="name" class="block text-gray-700 font-semibold">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $admin->name) }}"
                   class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        </div>
    
        <!-- Email -->
        <div class="mb-4">
            <label for="email" class="block text-gray-700 font-semibold">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $admin->email) }}"
                   class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        </div>
    
        <!-- Roles -->
        <div class="mb-4">
            <label for="roles" class="block text-gray-700 font-semibold">Roles</label>
            <select name="roles[]" id="roles" multiple
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" 
                        {{ in_array($role->id, old('roles', $admin->roles->pluck('id')->toArray())) ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
        </div>
    
        <!-- Permissions (optional) -->
        <div class="mb-4">
            <label for="permissions" class="block text-gray-700 font-semibold">Permissions</label>
            <select name="permissions[]" id="permissions" multiple
                    class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
                @foreach($permissions as $permission)
                    <option value="{{ $permission->id }}" 
                        {{ in_array($permission->id, old('permissions', $admin->permissions->pluck('id')->toArray())) ? 'selected' : '' }}>
                        {{ $permission->name }}
                    </option>
                @endforeach
            </select>
        </div>
    
        <!-- Submit Button -->
        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg">Update Admin</button>
    </form>
    
</div>
@endsection
