<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use App\Support\Auth\DefaultAccessProvisioner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class DefaultAccessProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_prepopulate_configured_users_or_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('tenants', ['name' => 'mTrack Demo Fleet']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_magic_link_request_provisions_configured_user_and_assigns_roles(): void
    {
        Notification::fake();

        $configuredUser = config('mtrack.default_access.users.0');

        $this->post('/auth/magic-link', [
            'email' => mb_strtoupper($configuredUser['email']),
        ])->assertSessionHas('status');

        $user = User::query()
            ->where('email', $configuredUser['email'])
            ->firstOrFail()
            ->load('roles');

        $this->assertNull($user->tenant_id);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->isPlatformAdmin());
        $this->assertSame(['platform-admin'], $user->roles->pluck('slug')->all());
        Notification::assertSentTo($user, MagicLoginLink::class);
    }

    public function test_tenant_configured_user_is_created_with_configured_tenant_role(): void
    {
        $configuredUser = collect(config('mtrack.default_access.users'))
            ->first(fn (array $user): bool => $user['tenant'] === 'demo');

        $user = app(DefaultAccessProvisioner::class)
            ->provisionForEmail($configuredUser['email'])
            ?->load('roles', 'tenant');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('mTrack Demo Fleet', $user->tenant?->name);
        $this->assertTrue($user->hasPermission('billing', 'edit'));
        $this->assertSame(['tenant-admin'], $user->roles->pluck('slug')->all());
        $this->assertTrue(Role::query()->where('slug', 'tenant-admin')->exists());
        $this->assertTrue(Tenant::query()->where('name', 'mTrack Demo Fleet')->exists());
    }

    public function test_unconfigured_magic_link_email_is_not_registered(): void
    {
        Notification::fake();

        $this->post('/auth/magic-link', [
            'email' => 'stranger@example.com',
        ])->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);
        Notification::assertNothingSent();
    }

    public function test_invalid_default_access_config_is_rejected_before_provisioning(): void
    {
        Config::set('mtrack.default_access.users.0.roles', ['missing-role']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('references an unknown role');

        app(DefaultAccessProvisioner::class)->provisionForEmail(config('mtrack.default_access.users.0.email'));
    }
}
