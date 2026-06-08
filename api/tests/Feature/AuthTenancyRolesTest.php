<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTenancyRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_auth_columns_are_not_created(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    public function test_magic_link_is_not_sent_to_user_in_inactive_tenant(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['status' => 'blocked']);
        $user = User::factory()->for($tenant)->create([
            'email' => 'blocked@example.com',
        ]);

        $this->post('/auth/magic-link', [
            'email' => 'blocked@example.com',
        ])->assertSessionHas('status');

        Notification::assertNotSentTo($user, MagicLoginLink::class);
    }

    public function test_tenant_admin_can_create_custom_role_and_audit_log(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->tenantUser($tenant, [
            'modules' => [
                'settings' => 'edit',
                'live' => 'edit',
                'billing' => 'view',
            ],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Dispatcher',
            'permissions' => [
                'modules' => [
                    'settings' => 'view',
                    'live' => 'view',
                ],
                'fleet_groups' => [
                    '12' => [
                        'live' => 'view',
                    ],
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.scope', 'tenant')
            ->assertJsonPath('data.permissions.modules.live', 'view');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id' => $admin->id,
            'action' => 'role.created',
        ]);
    }

    public function test_role_index_is_filtered_to_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $admin = $this->tenantUser($tenant);
        $visibleRole = Role::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'visible']);
        $hiddenRole = Role::factory()->create(['tenant_id' => $otherTenant->id, 'slug' => 'hidden']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/roles')->assertOk();

        $response->assertJsonFragment(['id' => $visibleRole->id]);
        $response->assertJsonMissing(['id' => $hiddenRole->id]);
    }

    public function test_cross_tenant_role_access_is_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $admin = $this->tenantUser($tenant);
        $otherRole = Role::factory()->create(['tenant_id' => $otherTenant->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/roles/{$otherRole->id}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_tenant_admin_cannot_grant_more_access_than_they_have(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->tenantUser($tenant, [
            'modules' => [
                'settings' => 'edit',
                'live' => 'view',
            ],
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/roles', [
            'name' => 'Fleet Editor',
            'permissions' => [
                'modules' => [
                    'live' => 'edit',
                ],
            ],
        ])->assertUnprocessable();
    }

    public function test_cross_tenant_role_assignment_is_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $admin = $this->tenantUser($tenant);
        $otherUser = User::factory()->for($otherTenant)->create();
        $role = Role::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$otherUser->id}/roles", [
            'role_ids' => [$role->id],
        ])->assertForbidden();
    }

    public function test_available_role_must_belong_to_users_tenant_when_assigning(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $admin = $this->tenantUser($tenant);
        $operator = User::factory()->for($tenant)->create();
        $otherRole = Role::factory()->create(['tenant_id' => $otherTenant->id]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$operator->id}/roles", [
            'role_ids' => [$otherRole->id],
        ])->assertUnprocessable();
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function tenantUser(Tenant $tenant, array $permissions = []): User
    {
        $permissions = $permissions ?: [
            'modules' => collect(config('mtrack.modules.customer'))
                ->mapWithKeys(fn (string $module) => [$module => 'edit'])
                ->all(),
        ];

        $role = Role::factory()->create([
            'tenant_id' => $tenant->id,
            'permissions' => $permissions,
        ]);

        $user = User::factory()->for($tenant)->create();
        $user->roles()->attach($role);

        return $user->load('roles', 'tenant');
    }
}
