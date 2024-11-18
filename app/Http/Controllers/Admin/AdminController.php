<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::paginate(10);
        return view('admin.index', compact('admins'));
    }

    public function create()
    {
        return view('admin.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'roles' => 'nullable|array',
        ]);

        $admin = Admin::create($request->only('name', 'email'));
        $admin->roles()->sync($request->input('roles', []));

        return redirect()->route('admin.index')->with('success', 'Admin created successfully.');
    }

    public function edit(Admin $admin)
    { 
        $roles = Role::all();
        $permissions = Permission::all();
        return view('admin.edit', compact('admin', 'roles', 'permissions'));
    }

    public function update(Request $request, Admin $admin)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,' . $admin->id,
            'roles' => 'nullable|array',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // $admin->update($request->only('name', 'email'));
        // $admin->roles()->sync($request->input('roles', []));

        // $validated = $request->validate([
        //     'name' => 'required|string|max:255',
        //     'email' => 'required|email|max:255',
        //     'roles' => 'nullable|array',
        //     'roles.*' => 'exists:roles,id',
        // ]);
    
        // $admin->update([
        //     'name' => $validated['name'],
        //     'email' => $validated['email'],
        // ]);
    
        // Sync roles and permissions
        $admin->syncRoles($validated['roles'] ?? []);
        $admin->syncPermissions($validated['permissions'] ?? []);
        return redirect()->route('admin.index')->with('success', 'Admin updated successfully.');
    }

    public function destroy(Admin $admin)
    {
        $admin->delete();
        return redirect()->route('admin.index')->with('success', 'Admin deleted successfully.');
    }
}
