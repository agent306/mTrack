<?php

namespace Tests\Feature;

use App\Models\{AlertEvent, AuditLog, DeviceConnection, Geofence, Role, Tenant, TrackerDevice, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FourPageWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $admin = false): User
    {
        $user = User::factory()->create(['tenant_id' => $admin ? null : Tenant::factory()->create()->id]);
        $role = Role::factory()->create(['tenant_id' => $user->tenant_id, 'scope' => $admin ? 'platform' : 'tenant', 'permissions' => ['modules' => ['*' => 'edit']]]);
        $user->roles()->attach($role);
        return $user;
    }

    public function test_both_surfaces_only_expose_four_pages_and_legacy_logs_redirect(): void
    {
        foreach ([false, true] as $admin) {
            $user = $this->user($admin);
            $base = $admin ? '/admin' : '/customer';
            foreach (['dashboard', 'events', 'devices', 'geofence'] as $page) {
                $this->actingAs($user)->get($base.'/'.$page)->assertOk()->assertInertia(fn ($p) => $p
                    ->component('Customer/Workspace', false)->where('isAdmin', $admin)
                    ->where('allowedModules', ['dashboard', 'events', 'my_fleets', 'geofence']));
            }
            $this->get($base.'/settings')->assertNotFound();
            $this->get($base.'/billing')->assertNotFound();
            $this->get($base.($admin ? '/logs' : '/audit-log'))->assertRedirect($base.'/events');
        }
    }

    public function test_events_include_status_and_tenant_logs_without_secret_metadata(): void
    {
        $owner = $this->user(); $other = $this->user();
        foreach ([$owner, $other] as $user) {
            $tracker = TrackerDevice::factory()->create(['tenant_id' => $user->tenant_id]);
            AlertEvent::create(['tenant_id' => $user->tenant_id, 'tracker_device_id' => $tracker->id, 'type' => 'online', 'occurred_at' => now()]);
            AuditLog::create(['tenant_id' => $user->tenant_id, 'actor_id' => $user->id, 'action' => 'device.updated', 'metadata' => ['secret' => 'must-not-leak']]);
        }
        $this->actingAs($owner)->get('/customer/events?tab=activity')->assertOk()->assertInertia(fn ($p) => $p
            ->has('events.data', 1)->where('events.data.0.type', 'online')->has('activity.data', 1)
            ->where('activity.data.0.actor', $owner->name)->missing('activity.data.0.metadata')->where('ingestion', null));
        $admin = $this->user(true);
        $this->actingAs($admin)->get('/admin/events?tenant='.$owner->tenant_id)->assertOk()->assertInertia(fn ($p) => $p
            ->has('events.data', 1)->has('activity.data', 1));
    }

    public function test_geofence_device_selection_cannot_cross_tenants_and_admin_requires_customer(): void
    {
        $owner = $this->user(); $other = $this->user();
        $mine = TrackerDevice::factory()->create(['tenant_id' => $owner->tenant_id]);
        $hidden = TrackerDevice::factory()->create(['tenant_id' => $other->tenant_id]);
        $data = ['name' => 'Selected boundary', 'shape_type' => 'polygon', 'shape_geometry' => ['points' => [[0,0], [0,1], [1,1]]], 'entrance_alert_enabled' => true, 'exit_alert_enabled' => false, 'tracker_ids' => [$hidden->id]];
        $this->actingAs($owner)->post('/customer/places', $data)->assertSessionHasErrors('tracker_ids.0');
        $data['tracker_ids'] = [$mine->id];
        $this->post('/customer/places', $data)->assertSessionHasNoErrors();
        $fence = Geofence::firstOrFail();
        $this->assertSame([$mine->id], $fence->trackerDevices->pluck('id')->all());
        $this->actingAs($this->user(true))->post('/admin/places', $data)->assertSessionHasErrors('tenant_id');
        $data['tenant_id'] = $other->tenant_id;
        $this->post('/admin/places', $data)->assertSessionHasErrors('tracker_ids.0');
    }

    public function test_native_assignment_is_admin_only_and_cannot_overwrite_ownership(): void
    {
        $owner = $this->user();
        $connection = DeviceConnection::create(['imei' => '123456789012345', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $data = ['tenant_id' => $owner->tenant_id, 'display_name' => 'Assigned tracker'];
        $this->actingAs($owner)->post('/admin/connections/'.$connection->id.'/assign', $data)->assertForbidden();
        $this->actingAs($this->user(true))->post('/admin/connections/'.$connection->id.'/assign', $data)->assertSessionHasNoErrors();
        $this->assertNotNull($connection->fresh()->tracker_device_id);
        $this->post('/admin/connections/'.$connection->id.'/assign', $data)->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $owner->tenant_id, 'action' => 'device.assigned']);
    }

    public function test_dashboard_preferences_are_validated_and_personal(): void
    {
        $user = $this->user(true);
        $this->actingAs($user)->post('/admin/dashboard/preferences', ['widgets' => ['map'], 'density' => 'compact', 'refresh_seconds' => 0])->assertSessionHasNoErrors();
        $this->assertSame(0, $user->fresh()->dashboard_preferences['refresh_seconds']);
        $this->post('/admin/dashboard/preferences', ['widgets' => ['map'], 'refresh_seconds' => 1])->assertSessionHasErrors('refresh_seconds');
    }

    public function test_dashboard_permission_does_not_grant_device_or_log_access(): void
    {
        $user = $this->user();
        $user->roles->first()->update(['permissions' => ['modules' => ['dashboard' => 'view', 'events' => 'view']]]);
        $user->unsetRelation('roles');
        TrackerDevice::factory()->create(['tenant_id' => $user->tenant_id]);
        $this->actingAs($user)->get('/customer/dashboard')->assertInertia(fn ($p) => $p->where('allowedModules', ['dashboard', 'events']));
        $this->get('/customer/devices')->assertForbidden();
        $this->get('/customer/events?tab=activity')->assertForbidden();
        $this->get('/customer/events?tab=ingestion')->assertForbidden();
    }
}
