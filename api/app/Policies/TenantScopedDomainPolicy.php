<?php

namespace App\Policies;

use App\Models\TenantScopedModel;
use App\Models\User;
use App\Support\Authorization\PermissionMatrix;

abstract class TenantScopedDomainPolicy
{
    protected string $module = 'dashboard';

    public function before(User $user): ?bool
    {
        return $user->isPlatformAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && $user->hasPermission($this->module, PermissionMatrix::VIEW);
    }

    public function view(User $user, TenantScopedModel $model): bool
    {
        return $this->sameTenant($user, $model)
            && $user->hasPermission($this->module, PermissionMatrix::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->tenant_id !== null
            && $user->hasPermission($this->module, PermissionMatrix::EDIT);
    }

    public function update(User $user, TenantScopedModel $model): bool
    {
        return $this->sameTenant($user, $model)
            && $user->hasPermission($this->module, PermissionMatrix::EDIT);
    }

    public function delete(User $user, TenantScopedModel $model): bool
    {
        return $this->update($user, $model);
    }

    private function sameTenant(User $user, TenantScopedModel $model): bool
    {
        return $user->isActive()
            && $user->tenant_id !== null
            && $model->tenant_id === $user->tenant_id;
    }
}
