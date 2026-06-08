<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionMatrix;

class RolePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isPlatformAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('settings', PermissionMatrix::VIEW);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->sameTenant($user, $role)
            && $user->hasPermission('settings', PermissionMatrix::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->tenant_id !== null
            && $user->hasPermission('settings', PermissionMatrix::EDIT);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->sameTenant($user, $role)
            && $role->scope === 'tenant'
            && $user->hasPermission('settings', PermissionMatrix::EDIT);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->update($user, $role);
    }

    private function sameTenant(User $user, Role $role): bool
    {
        return $user->isActive()
            && $user->tenant_id !== null
            && $role->tenant_id === $user->tenant_id;
    }
}
