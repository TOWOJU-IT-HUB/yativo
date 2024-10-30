<?php

namespace App\Services;
use Spatie\Permission\Models\Role;
use Laravel\Jetstream\Jetstream;


class RoleService
{
    public function syncJetstreamRoles()
    {
        $roles = Role::with('permissions')->get();
        foreach ($roles as $role) {
            $permissions = $role->permissions->pluck('name')->toArray();
            Jetstream::role($role->name, $role->display_name ?? ucfirst($role->name), $permissions)->description($role->description ?? ucfirst($role->name));
        }
    }
}