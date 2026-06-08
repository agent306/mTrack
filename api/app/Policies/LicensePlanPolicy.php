<?php

namespace App\Policies;

use App\Models\LicensePlan;
use App\Models\User;
use App\Support\Authorization\PermissionMatrix;

class LicensePlanPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isPlatformAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && $user->hasPermission('billing', PermissionMatrix::VIEW);
    }

    public function view(User $user, LicensePlan $licensePlan): bool
    {
        return $this->viewAny($user) && $licensePlan->status === 'active';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LicensePlan $licensePlan): bool
    {
        return false;
    }

    public function delete(User $user, LicensePlan $licensePlan): bool
    {
        return false;
    }
}
