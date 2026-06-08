<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'mTrack Demo Fleet',
        ]);

        $role = Role::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant-admin',
            'permissions' => [
                'modules' => collect(config('mtrack.modules.customer'))
                    ->mapWithKeys(fn (string $module) => [$module => 'edit'])
                    ->all(),
            ],
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'mTrack Operator',
            'email' => 'test@example.com',
        ]);

        $user->roles()->attach($role);
    }
}
