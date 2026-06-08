<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('assignRoles', $user);

        $validated = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer'],
        ]);

        $roles = Role::withoutGlobalScopes()
            ->whereIn('id', $validated['role_ids'])
            ->when(! $request->user()->isPlatformAdmin(), fn ($query) => $query->where('tenant_id', $request->user()->tenant_id))
            ->get();

        if ($roles->count() !== count(array_unique($validated['role_ids']))) {
            throw ValidationException::withMessages([
                'role_ids' => 'One or more roles are not available in this tenant.',
            ]);
        }

        $this->authorizeAssignableRoles($request->user(), $roles);

        $user->roles()->sync($roles->pluck('id')->all());

        AuditLog::query()->create([
            'tenant_id' => $user->tenant_id,
            'actor_id' => $request->user()->id,
            'action' => 'user.roles.updated',
            'subject_type' => $user::class,
            'subject_id' => $user->id,
            'metadata' => [
                'role_ids' => $roles->pluck('id')->all(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'data' => $user->load('roles'),
        ]);
    }

    private function authorizeAssignableRoles(User $actor, iterable $roles): void
    {
        if ($actor->isPlatformAdmin()) {
            return;
        }

        foreach ($roles as $role) {
            foreach ($this->requestedPermissions($role->permissions) as [$module, $level]) {
                if (! $actor->hasPermission($module, PermissionMatrix::normalize($level))) {
                    throw ValidationException::withMessages([
                        'role_ids' => "You cannot assign a role that grants {$level} access to {$module}.",
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $permissions
     * @return array<int, array{0: string, 1: string}>
     */
    private function requestedPermissions(array $permissions): array
    {
        $requested = [];

        foreach (($permissions['modules'] ?? []) as $module => $level) {
            $requested[] = [$module, $level];
        }

        foreach (['fleet_groups', 'trackers'] as $scope) {
            foreach (($permissions[$scope] ?? []) as $scopeRules) {
                foreach ($scopeRules as $module => $level) {
                    $requested[] = [$module, $level];
                }
            }
        }

        return $requested;
    }
}
