<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\PermissionMatrix;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isPlatformAdmin() ? true : null;
    }

    public function assignRoles(User $user, User $target): bool
    {
        return $user->isActive()
            && $user->tenant_id !== null
            && $target->tenant_id === $user->tenant_id
            && $user->hasPermission('settings', PermissionMatrix::EDIT);
    }
}
