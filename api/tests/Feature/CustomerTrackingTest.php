<?php

namespace Tests\Feature;

use App\Models\{AlertEvent, Geofence, NormalizedLocationEvent, Role, Tenant, TrackerDevice, User};
use App\Support\Auth\CustomerRegistration;
use App\Tracking\{DeviceGateway, GeofenceEvaluator, TrackerStateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->for(Tenant::factory()->create())->create();
        $role = Role::factory()->create(['tenant_id' => $user->tenant_id, 'permissions' => ['modules' => ['*' => 'edit']]]);
        $user->roles()->attach($role);
        return $user;
    }

    public function test_signup_isolated_and_existing_accounts_not_reactivated(): void
    {
        $registration = app(CustomerRegistration::class);
        $first = $registration->register('one@example.test', 'One', 'one');
        $second = $registration->register('two@example.test', 'Two', 'two');
        $this->assertNotEquals($first->tenant_id, $second->tenant_id);
        $this->assertTrue($first->hasPermission('geofence', 'edit'));
        $first->update(['status' => 'blocked']);
        $this->assertSame('blocked', $registration->register('one@example.test', 'One', 'one')->status);
        $this->assertSame(2, Tenant::count());
    }

    public function test_gateway_authentication_and_deduplication(): void
    {
        config(['tracker.gateway_secret' => str_repeat('s', 40)]);
        $packet = ['imei' => '123456789012345', 'protocol' => 1, 'packet_hex' => '787801', 'iccid' => '89860012345678901234'];
        $this->postJson('/api/gateway/packets', $packet)->assertUnauthorized();
        $this->withToken(str_repeat('s', 40))->postJson('/api/gateway/packets', $packet)->assertOk();
        $this->withToken(str_repeat('s', 40))->postJson('/api/gateway/packets', $packet)->assertOk();
        $this->assertDatabaseCount('device_connections', 1);
        $this->assertDatabaseCount('device_packets', 1);
        $this->assertDatabaseMissing('device_connections', ['iccid_hash' => $packet['iccid']]);
    }

    public function test_claim_requires_proof_and_cannot_be_stolen(): void
    {
        $owner = $this->owner(); $other = $this->owner();
        $imei = '123456789012345'; $iccid = '89860012345678901234';
        app(DeviceGateway::class)->receive(['imei' => $imei, 'protocol' => 1, 'packet_hex' => '787801', 'iccid' => $iccid]);
        $this->actingAs($owner)->post('/customer/devices/discover', ['imei' => $imei, 'proof' => str_repeat('0', 20)])->assertSessionHasErrors('proof');
        $this->actingAs($owner)->post('/customer/devices/discover', ['imei' => $imei, 'proof' => $iccid])->assertSessionHas('device_discovery');
        $this->actingAs($owner)->post('/customer/devices/claim', ['imei' => $imei, 'proof' => $iccid, 'name' => 'My car'])->assertRedirect('/customer/devices');
        $this->assertSame($owner->tenant_id, TrackerDevice::withoutGlobalScopes()->firstOrFail()->tenant_id);
        $this->actingAs($other)->post('/customer/devices/claim', ['imei' => $imei, 'proof' => $iccid, 'name' => 'Stolen'])->assertSessionHasErrors('proof');
        $this->assertDatabaseCount('tracker_devices', 1);
    }

    public function test_claim_replays_locations_once_with_correct_speed(): void
    {
        $owner = $this->owner();
        $packet = ['imei' => '123456789012345', 'protocol' => 0xa0, 'packet_hex' => '7878aa', 'iccid' => '89860012345678901234',
            'location' => ['latitude' => 24.5, 'longitude' => 67.25, 'timestamp' => now()->subMinute()->toIso8601String(), 'speedKmh' => 36]];
        app(DeviceGateway::class)->receive($packet);
        $this->assertDatabaseCount('normalized_location_events', 0);
        $this->actingAs($owner)->post('/customer/devices/claim', ['imei' => $packet['imei'], 'proof' => $packet['iccid'], 'name' => 'My car'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('normalized_location_events', 1);
        app(DeviceGateway::class)->receive($packet);
        $this->assertDatabaseCount('normalized_location_events', 1);
        $event = NormalizedLocationEvent::withoutGlobalScopes()->first();
        $this->assertSame($owner->tenant_id, $event->tenant_id);
        $this->assertEquals(10, $event->speed);
    }

    public function test_geofence_baseline_transitions_and_old_packets(): void
    {
        $owner = $this->owner();
        $tracker = TrackerDevice::factory()->create(['tenant_id' => $owner->tenant_id, 'last_seen_at' => null]);
        $fence = Geofence::factory()->create(['tenant_id' => $owner->tenant_id, 'fleet_group_id' => null, 'shape_type' => 'circle',
            'shape_geometry' => ['center' => [0, 0], 'radius' => 200], 'entrance_alert_enabled' => true, 'exit_alert_enabled' => true]);
        $time = now()->subMinutes(10);
        foreach ([[0.01, 0], [0, 1], [0, 2], [0.01, 3], [0, 1]] as [$lat, $minute]) {
            $event = NormalizedLocationEvent::factory()->forTracker($tracker)->create(['latitude' => $lat, 'longitude' => 0, 'event_timestamp' => $time->copy()->addMinutes($minute)]);
            DB::transaction(fn () => app(TrackerStateService::class)->markLive($tracker, $event));
        }
        $this->assertSame(1, AlertEvent::where('type', 'geofence_entry')->count());
        $this->assertSame(1, AlertEvent::where('type', 'geofence_exit')->count());
        $this->assertFalse((bool) DB::table('geofence_states')->where('geofence_id', $fence->id)->value('inside'));
        $this->assertTrue(app(GeofenceEvaluator::class)->contains('polygon', ['points' => [[0,0],[0,1],[1,1],[1,0]]], .5, .5));
        $this->assertFalse(app(GeofenceEvaluator::class)->contains('polygon', ['points' => [[0,0],[0,1],[1,1],[1,0]]], 2, 2));
    }

    public function test_geometry_validation_and_cross_tenant_protection(): void
    {
        $owner = $this->owner(); $other = $this->owner();
        $data = ['name' => 'Home', 'shape_type' => 'circle', 'shape_geometry' => ['center' => [24.5, 67.25], 'radius' => 300], 'entrance_alert_enabled' => true, 'exit_alert_enabled' => true];
        $this->actingAs($owner)->post('/customer/places', $data)->assertSessionHasNoErrors();
        $fence = Geofence::withoutGlobalScopes()->first();
        $this->assertSame($data['shape_geometry'], $fence->shape_geometry);
        $this->actingAs($other)->put('/customer/places/'.$fence->id, $data)->assertForbidden();
        $data['shape_geometry']['center'][0] = 200;
        $this->actingAs($owner)->post('/customer/places', $data)->assertSessionHasErrors('shape_geometry.center.0');
    }

    public function test_personal_preferences_and_safe_scoped_reports(): void
    {
        $owner = $this->owner(); $other = $this->owner();
        $this->actingAs($owner)->post('/customer/dashboard/preferences', ['widgets' => ['map', 'summary']])->assertSessionHasNoErrors();
        $this->assertSame(['map', 'summary'], $owner->fresh()->dashboard_preferences['widgets']);
        $this->assertNull($other->fresh()->dashboard_preferences);
        $tracker = TrackerDevice::factory()->create(['tenant_id' => $owner->tenant_id, 'display_name' => '=UNSAFE()']);
        $hidden = TrackerDevice::factory()->create(['tenant_id' => $other->tenant_id, 'display_name' => 'Hidden device']);
        foreach ([$tracker, $hidden] as $t) AlertEvent::withoutGlobalScopes()->create(['tenant_id' => $t->tenant_id, 'tracker_device_id' => $t->id, 'type' => 'geofence_entry', 'occurred_at' => now()]);
        $url = '/customer/reports/places.csv?from='.now()->toDateString().'&to='.now()->toDateString();
        $csv = $this->actingAs($owner)->get($url)->assertOk()->streamedContent();
        $this->assertStringContainsString("'=UNSAFE()", $csv);
        $this->assertStringNotContainsString('Hidden device', $csv);
        $this->actingAs($owner)->get('/customer/dashboard')->assertInertia(fn ($p) => $p->component('Customer/Workspace', false)->has('trackers', 1));
    }
}
