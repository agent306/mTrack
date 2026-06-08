<?php

namespace Tests\Feature;

use App\Events\TrackerLocationUpdated;
use App\Models\NormalizedLocationEvent;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use App\Models\User;
use App\Tracking\TrackerStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeTrackingCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingestion_broadcasts_tracker_location_update_payload(): void
    {
        Event::fake([TrackerLocationUpdated::class]);

        $tenant = Tenant::factory()->create();
        TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'contract_key' => 'demo-json',
            'contract_version' => 1,
            'metadata' => ['device_identity' => 'fixture-tracker-001'],
            'status' => 'offline',
        ]);

        $this->postRawIngestion($this->fixture('demo-json-valid.json'))
            ->assertCreated();

        Event::assertDispatched(TrackerLocationUpdated::class, function (TrackerLocationUpdated $event) use ($tenant): bool {
            $payload = $event->broadcastWith();
            $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

            return $event->broadcastAs() === 'tracker.location.updated'
                && in_array("private-tenants.{$tenant->id}.trackers", $channels, true)
                && $payload['tenant_id'] === $tenant->id
                && $payload['event'] === 'tracker.location.updated'
                && $payload['latitude'] === 24.8607
                && $payload['longitude'] === 67.0011
                && $payload['status'] === 'moving';
        });
    }

    public function test_latest_tracker_state_is_tenant_scoped(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $operator = $this->operator($tenant, ['live' => 'view']);
        $tracker = TrackerDevice::factory()->create(['tenant_id' => $tenant->id]);
        $otherTracker = TrackerDevice::factory()->create(['tenant_id' => $otherTenant->id]);
        $event = NormalizedLocationEvent::factory()->forTracker($tracker)->create([
            'latitude' => 25.1234567,
            'longitude' => 67.7654321,
        ]);

        $tracker->forceFill([
            'last_event_id' => $event->id,
            'last_seen_at' => $event->event_timestamp,
            'status' => 'moving',
        ])->save();

        $response = $this->actingAs($operator, 'sanctum')
            ->getJson('/api/trackers/latest')
            ->assertOk()
            ->assertJsonPath('data.0.tracker_device_id', $tracker->id)
            ->assertJsonPath('data.0.last_event.normalized_location_event_id', $event->id);

        $response->assertJsonMissing(['tracker_device_id' => $otherTracker->id]);

        $this->actingAs($operator, 'sanctum')
            ->getJson("/api/trackers/{$otherTracker->id}/latest")
            ->assertNotFound();
    }

    public function test_live_permission_is_required_for_latest_state(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = $this->operator($tenant, ['live' => 'hide']);

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/trackers/latest')
            ->assertForbidden();
    }

    public function test_inactivity_scan_marks_no_data_stale_and_offline_once(): void
    {
        Config::set('mtrack.realtime.stale_after_minutes', 30);
        Config::set('mtrack.realtime.offline_after_minutes', 120);

        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $noData = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'online',
            'last_seen_at' => null,
        ]);
        $stale = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'moving',
            'last_seen_at' => now()->subMinutes(31),
        ]);
        $offline = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'moving',
            'last_seen_at' => now()->subMinutes(121),
        ]);
        $fresh = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'moving',
            'last_seen_at' => now()->subMinutes(5),
        ]);
        TrackerDevice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => 'moving',
            'last_seen_at' => now()->subMinutes(121),
        ]);

        $summary = app(TrackerStateService::class)->markInactiveTrackers($tenant->id);

        $this->assertSame(['no_data' => 1, 'stale' => 1, 'offline' => 1], $summary);
        $this->assertSame('no_data', $noData->refresh()->status);
        $this->assertSame('stale', $stale->refresh()->status);
        $this->assertSame('offline', $offline->refresh()->status);
        $this->assertSame('moving', $fresh->refresh()->status);
        $this->assertDatabaseCount('alert_events', 3);

        $secondSummary = app(TrackerStateService::class)->markInactiveTrackers($tenant->id);

        $this->assertSame(['no_data' => 0, 'stale' => 0, 'offline' => 0], $secondSummary);
        $this->assertDatabaseCount('alert_events', 3);
    }

    public function test_inactivity_command_runs_scan(): void
    {
        $tenant = Tenant::factory()->create();
        TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'moving',
            'last_seen_at' => now()->subMinutes(200),
        ]);

        $this->artisan('trackers:mark-inactive', ['--tenant-id' => $tenant->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('alert_events', [
            'tenant_id' => $tenant->id,
            'type' => 'offline',
        ]);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/Ingestion/{$name}"));
    }

    private function postRawIngestion(string $body)
    {
        return $this->call(
            method: 'POST',
            uri: '/api/ingest/demo-json',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
    }

    /**
     * @param  array<string, string>  $modulePermissions
     */
    private function operator(Tenant $tenant, array $modulePermissions): User
    {
        $role = Role::factory()->create([
            'tenant_id' => $tenant->id,
            'permissions' => [
                'modules' => $modulePermissions,
            ],
        ]);

        $user = User::factory()->for($tenant)->create();
        $user->roles()->attach($role);

        return $user->load('roles', 'tenant');
    }
}
