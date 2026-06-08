<?php

namespace App\Support\Auth;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class DefaultAccessProvisioner
{
    public function provisionForEmail(string $email, ?string $name = null, string $authProvider = 'magic_link'): ?User
    {
        $email = mb_strtolower($email);
        $access = $this->validatedAccessConfig();
        $userConfig = collect($access['users'])
            ->first(fn (array $user): bool => mb_strtolower($user['email']) === $email);

        if ($userConfig === null) {
            return null;
        }

        $tenants = $this->ensureTenants($access, Arr::wrap($userConfig['tenant']));
        $roles = $this->ensureRoles($access, $tenants, $userConfig['roles']);
        $tenantId = $userConfig['tenant'] === null ? null : $tenants[$userConfig['tenant']]->id;

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'tenant_id' => $tenantId,
            'name' => $name ?: $userConfig['name'],
            'email' => $email,
            'auth_provider' => $authProvider,
            'status' => 'active',
            'email_verified_at' => $authProvider === 'google' ? ($user->email_verified_at ?? now()) : $user->email_verified_at,
        ])->save();

        $user->roles()->syncWithoutDetaching(
            collect($userConfig['roles'])
                ->map(fn (string $roleKey): int => $roles[$roleKey]->id)
                ->all()
        );

        return $user->load('tenant', 'roles');
    }

    public function ensureTenant(string $tenantKey): Tenant
    {
        $access = $this->validatedAccessConfig();

        if (! array_key_exists($tenantKey, $access['tenants'])) {
            throw new InvalidArgumentException("mtrack.default_access.tenants.{$tenantKey} is not configured.");
        }

        return $this->ensureTenants($access, [$tenantKey])[$tenantKey];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAccessConfig(): array
    {
        $access = config('mtrack.default_access', []);

        foreach (['tenants', 'roles', 'users'] as $key) {
            if (! array_key_exists($key, $access) || ! is_array($access[$key])) {
                throw new InvalidArgumentException("mtrack.default_access.{$key} must be configured.");
            }
        }

        foreach ($access['roles'] as $roleKey => $role) {
            if (! array_key_exists('tenant', $role) || ! array_key_exists('slug', $role)) {
                throw new InvalidArgumentException("mtrack.default_access.roles.{$roleKey} must include tenant and slug.");
            }

            if ($role['tenant'] !== null && ! array_key_exists($role['tenant'], $access['tenants'])) {
                throw new InvalidArgumentException("mtrack.default_access.roles.{$roleKey} references an unknown tenant.");
            }
        }

        foreach ($access['users'] as $index => $user) {
            if (! array_key_exists('email', $user) || ! array_key_exists('tenant', $user) || ! array_key_exists('roles', $user) || ! is_array($user['roles'])) {
                throw new InvalidArgumentException("mtrack.default_access.users.{$index} must include email, tenant, and roles.");
            }

            if ($user['tenant'] !== null && ! array_key_exists($user['tenant'], $access['tenants'])) {
                throw new InvalidArgumentException("mtrack.default_access.users.{$index} references an unknown tenant.");
            }

            foreach ($user['roles'] as $roleKey) {
                if (! array_key_exists($roleKey, $access['roles'])) {
                    throw new InvalidArgumentException("mtrack.default_access.users.{$index} references an unknown role.");
                }

                if ($access['roles'][$roleKey]['tenant'] !== $user['tenant']) {
                    throw new InvalidArgumentException("mtrack.default_access.users.{$index} references a role from another tenant scope.");
                }
            }
        }

        return $access;
    }

    /**
     * @param  array<string, mixed>  $access
     * @param  array<int, string|null>  $tenantKeys
     * @return array<string, Tenant>
     */
    private function ensureTenants(array $access, array $tenantKeys): array
    {
        $tenants = [];

        foreach (array_filter(array_unique($tenantKeys)) as $tenantKey) {
            $attributes = $access['tenants'][$tenantKey];
            $tenants[$tenantKey] = Tenant::query()->updateOrCreate(
                ['name' => $attributes['name']],
                [
                    'status' => $attributes['status'],
                    'billing_status' => $attributes['billing_status'],
                    'raw_payload_retention_days' => $attributes['raw_payload_retention_days'],
                ]
            );
        }

        return $tenants;
    }

    /**
     * @param  array<string, mixed>  $access
     * @param  array<string, Tenant>  $tenants
     * @param  array<int, string>  $roleKeys
     * @return array<string, Role>
     */
    private function ensureRoles(array $access, array $tenants, array $roleKeys): array
    {
        $roles = [];

        foreach ($roleKeys as $roleKey) {
            $attributes = $access['roles'][$roleKey];
            $tenantId = $attributes['tenant'] === null ? null : $tenants[$attributes['tenant']]->id;
            $role = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('slug', $attributes['slug'])
                ->firstOrNew();

            $role->forceFill([
                'tenant_id' => $tenantId,
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'scope' => $attributes['scope'],
                'permissions' => $attributes['permissions'],
            ])->save();

            $roles[$roleKey] = $role;
        }

        return $roles;
    }
}
