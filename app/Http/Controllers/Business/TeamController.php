<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function getOwner(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            return get_success_response(['owner' => $team->owner]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get team owner: ' . $e->getMessage()]);
        }
    }

    public function getUsers(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            return get_success_response(['users' => $team->users()]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get team users: ' . $e->getMessage()]);
        }
    }

    public function getAllUsers(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            return get_success_response(['all_users' => $team->allUsers()]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get all team users: ' . $e->getMessage()]);
        }
    }

    public function hasUser(Request $request, $teamId, $userId)
    {
        try {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);
            return get_success_response(['has_user' => $team->hasUser($user)]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to check if user is in team: ' . $e->getMessage()]);
        }
    }

    public function getAbilities(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            return get_success_response(['abilities' => $team->abilities()]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get team abilities: ' . $e->getMessage()]);
        }
    }

    public function getRoles(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            return get_success_response(['roles' => $team->roles()]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get team roles: ' . $e->getMessage()]);
        }
    }

    public function findRole(Request $request, $teamId, $roleId)
    {
        try {
            $team = Team::findOrFail($teamId);
            $role = $team->findRole($roleId);
            return get_success_response(['role' => $role]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to find role: ' . $e->getMessage()]);
        }
    }

    public function getUserRole(Request $request, $teamId, $userId)
    {
        try {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);
            return get_success_response(['user_role' => $team->userRole($user)]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get user role: ' . $e->getMessage()]);
        }
    }

    public function addRole(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            $validated = $request->validate([
                'name' => 'required|string',
                'capabilities' => 'required|array',
            ]);
            $role = $team->addRole($validated['name'], $validated['capabilities']);
            return get_success_response(['role' => $role]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to add role: ' . $e->getMessage()]);
        }
    }

    public function updateRole(Request $request, $teamId, $roleName)
    {
        try {
            $team = Team::findOrFail($teamId);
            $validated = $request->validate([
                'capabilities' => 'required|array',
            ]);
            $role = $team->updateRole($roleName, $validated['capabilities']);
            return get_success_response(['role' => $role]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to update role: ' . $e->getMessage()]);
        }
    }

    public function deleteRole(Request $request, $teamId, $roleName)
    {
        try {
            $team = Team::findOrFail($teamId);
            $team->deleteRole($roleName);
            return get_success_response(['message' => 'Role deleted successfully']);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to delete role: ' . $e->getMessage()]);
        }
    }

    public function getGroups(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            return get_success_response(['groups' => $team->groups()]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get groups: ' . $e->getMessage()]);
        }
    }

    public function getGroup(Request $request, $teamId, $groupCode)
    {
        try {
            $team = Team::findOrFail($teamId);
            return get_success_response(['group' => $team->group($groupCode)]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get group: ' . $e->getMessage()]);
        }
    }

    public function addGroup(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            $validated = $request->validate([
                'code' => 'required|string',
                'name' => 'required|string',
            ]);
            $group = $team->addGroup($validated['code'], $validated['name']);
            return get_success_response(['group' => $group]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to add group: ' . $e->getMessage()]);
        }
    }

    public function deleteGroup(Request $request, $teamId, $groupCode)
    {
        try {
            $team = Team::findOrFail($teamId);
            $team->deleteGroup($groupCode);
            return get_success_response(['message' => 'Group deleted successfully']);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to delete group: ' . $e->getMessage()]);
        }
    }

    public function hasUserWithEmail(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            $validated = $request->validate(['email' => 'required|email']);
            $hasUser = $team->hasUserWithEmail($validated['email']);
            return get_success_response(['has_user_with_email' => $hasUser]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to check user email: ' . $e->getMessage()]);
        }
    }

    public function userHasPermission(Request $request, $teamId, $userId, $permission)
    {
        try {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);
            $hasPermission = $team->userHasPermission($user, $permission);
            return get_success_response(['user_has_permission' => $hasPermission]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to check user permission: ' . $e->getMessage()]);
        }
    }

    public function deleteUser(Request $request, $teamId, $userId)
    {
        try {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);
            $team->deleteUser($user);
            return get_success_response(['message' => 'User removed from team successfully']);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to delete user from team: ' . $e->getMessage()]);
        }
    }

    public function getInvitations(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            return get_success_response(['invitations' => $team->invitations()]);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to get invitations: ' . $e->getMessage()]);
        }
    }

    // public function authenticateStaff(Request $request)
    // {
    //     try {
    //         $credentials = $request->validate([
    //             'email' => 'required|email',
    //             'password' => 'required',
    //         ]);

    //         if (Auth::attempt($credentials)) {
    //             $user = Auth::user();
    //             $token = $user->createToken('auth_token')->plainTextToken;
    //             return get_success_response(['token' => $token]);
    //         }

    //         return get_error_response(['error' => 'Invalid credentials']);
    //     } catch (\Exception $e) {
    //         return get_error_response(['error' => 'Authentication failed: ' . $e->getMessage()]);
    //     }
    // }

    public function assignRole(Request $request, $teamId, $userId, $roleName)
    {
        try {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);
            $role = $team->findRole($roleName);
            if (!$role) {
                return get_error_response(['error' => 'Role not found']);
            }
            $user->assignRole($role);
            return get_success_response(['message' => 'Role assigned successfully']);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to assign role: ' . $e->getMessage()]);
        }
    }

    public function revokeRole(Request $request, $teamId, $userId, $roleName)
    {
        try {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);
            $role = $team->findRole($roleName);
            if (!$role) {
                return get_error_response(['error' => 'Role not found']);
            }
            $user->removeRole($role);
            return get_success_response(['message' => 'Role revoked successfully']);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to revoke role: ' . $e->getMessage()]);
        }
    }

    public function givePermissionTo(Request $request, $teamId, $userId, $permission)
    {
        try {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);
            $user->givePermissionTo($permission);
            return get_success_response(['message' => 'Permission granted successfully']);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to grant permission: ' . $e->getMessage()]);
        }
    }

    public function revokePermissionTo(Request $request, $teamId, $userId, $permission)
    {
        try {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);
            $user->revokePermissionTo($permission);
            return get_success_response(['message' => 'Permission revoked successfully']);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Failed to revoke permission: ' . $e->getMessage()]);
        }
    }
}
