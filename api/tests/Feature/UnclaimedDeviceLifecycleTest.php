<?php

namespace Tests\Feature;

use App\Models\DeviceConnection;
use App\Models\NormalizedLocationEvent;
use App\Models\RawPayload;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnclaimedDeviceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const V6 = '*HQ,9171701065,V6,123350,A,0413.6411,N,07332.6347,E,010.00,090,061026,FFFFFBFF,472,02,8000,24963,8996002235202820115F#';

    private function ingest(string $body)
    {
        return $this->call('POST', '/api/ingest', server: ['CONTENT_TYPE' => 'application/octet-stream'], content: $body);
    }

    private function customer(): User
    {
        $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $user->roles()->attach(Role::factory()->create(['tenant_id' => $user->tenant_id, 'scope' => 'tenant', 'permissions' => ['modules' => ['*' => 'edit']]]));

        return $user;
    }

    public function test_valid_data_from_unknown_device_is_held_and_becomes_claimable(): void
    {
        $this->ingest(self::V6)->assertStatus(202)->assertJsonPath('status', 'pending_claim');

        $device = DeviceConnection::where('imei', '9171701065')->firstOrFail();
        $this->assertNull($device->tracker_device_id);
        $this->assertSame(hash('sha256', '8996002235202820115'), $device->iccid_hash);
        $this->assertSame(1, DB::table('device_packets')->where('device_connection_id', $device->id)->count());
        $this->assertSame('pending_claim', RawPayload::withoutGlobalScope('tenant')->firstOrFail()->processing_status);
        $this->assertDatabaseCount('normalized_location_events', 0);

        $user = $this->customer();
        $this->actingAs($user)->post('/customer/devices/claim', [
            'imei' => '9171701065', 'proof' => '8996 0022 3520 2820 115', 'name' => 'Boat',
        ])->assertRedirect('/customer/devices');

        $tracker = TrackerDevice::withoutGlobalScopes()->where('metadata->device_identity', '9171701065')->firstOrFail();
        $this->assertSame($user->tenant_id, $tracker->tenant_id);
        $event = NormalizedLocationEvent::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($tracker->id, $event->tracker_device_id);
        $this->assertEqualsWithDelta(4.227352, (float) $event->latitude, 0.00001);

        // Once claimed, new frames normalize directly.
        $this->ingest(str_replace('123350', '123450', self::V6))->assertCreated()->assertJsonPath('tracker_device_id', $tracker->id);
    }

    public function test_non_numeric_unknown_identity_is_still_rejected(): void
    {
        $this->ingest(json_encode(['imei' => 'JIMI-1', 'lat' => 4.2, 'lng' => 73.5, 'timestamp' => now()->toIso8601String()]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('device_connections', 0);
    }

    public function test_devices_unclaimed_after_24_hours_are_disposed_with_their_data(): void
    {
        $this->travelTo(now()->subHours(25));
        $this->ingest(self::V6)->assertStatus(202);
        $this->travelBack();
        $this->ingest(str_replace('9171701065', '9171701066', self::V6))->assertStatus(202);

        $this->artisan('mtrack:prune-unclaimed-devices')->assertSuccessful();

        $this->assertDatabaseMissing('device_connections', ['imei' => '9171701065']);
        $this->assertDatabaseHas('device_connections', ['imei' => '9171701066']);
        $this->assertSame(1, DB::table('device_packets')->count());
        $this->assertSame(['9171701066'], RawPayload::withoutGlobalScope('tenant')->get()->pluck('metadata.pending_device_identity')->all());
    }

    public function test_claimed_devices_are_never_disposed(): void
    {
        $tracker = TrackerDevice::factory()->create();
        DeviceConnection::create(['imei' => '123456789012345', 'tracker_device_id' => $tracker->id, 'first_seen_at' => now()->subDays(3), 'last_seen_at' => now()]);

        $this->artisan('mtrack:prune-unclaimed-devices')->assertSuccessful();

        $this->assertDatabaseHas('device_connections', ['imei' => '123456789012345']);
    }
}
