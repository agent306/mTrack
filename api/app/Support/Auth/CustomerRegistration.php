<?php

namespace App\Support\Auth;

use App\Models\{Role, Tenant, User};
use Illuminate\Support\Facades\DB;

class CustomerRegistration
{
    public function register(string $email, string $name, string $googleId): User
    {
        return DB::transaction(function () use ($email, $name, $googleId) {
            // Never change access or tenancy for an existing account.
            $existing = User::where('email', $email)->first();
            if ($existing) {
                return $existing;
            }
            $tenant = Tenant::create([
                'name' => $name."'s workspace", 'status' => 'active',
                'billing_status' => 'trial', 'raw_payload_retention_days' => 30,
            ]);
            $role = Role::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'name' => 'Owner', 'slug' => 'customer-owner',
                'scope' => 'tenant', 'permissions' => ['modules' => ['*' => 'edit']],
            ]);
            $user = User::create([
                'tenant_id' => $tenant->id, 'name' => $name, 'email' => $email,
                'auth_provider' => 'google', 'google_id' => $googleId, 'status' => 'active',
            ]);
            $user->roles()->attach($role);
            return $user;
        });
    }
}
