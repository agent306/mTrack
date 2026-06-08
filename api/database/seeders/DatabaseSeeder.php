<?php

namespace Database\Seeders;

use App\Models\AlertEvent;
use App\Models\FleetGroup;
use App\Models\Geofence;
use App\Models\LicenseAllocation;
use App\Models\LicensePlan;
use App\Models\LicenseRequest;
use App\Models\NormalizedLocationEvent;
use App\Models\PaymentSlip;
use App\Models\RawPayload;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TrackerDevice;
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

        TenantSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'key' => 'raw_payload.retention',
            'value' => ['days' => $tenant->raw_payload_retention_days],
        ]);

        $group = FleetGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Harbor Operations',
            'visibility' => 'private',
        ]);

        $tracker = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'display_name' => 'Demo Vessel Tracker',
            'business_label' => 'vessel',
            'status' => 'moving',
            'last_seen_at' => now(),
            'metadata' => [
                'device_identity' => 'demo-vessel-001',
            ],
        ]);

        $group->trackerDevices()->attach($tracker->id, ['tenant_id' => $tenant->id]);

        $rawPayload = RawPayload::factory()
            ->forTracker($tracker)
            ->create(['processing_status' => 'normalized']);

        $event = NormalizedLocationEvent::factory()
            ->forTracker($tracker, $rawPayload)
            ->create([
                'latitude' => 24.8607,
                'longitude' => 67.0011,
                'speed' => 42.50,
            ]);

        $tracker->forceFill([
            'last_event_id' => $event->id,
            'last_seen_at' => $event->event_timestamp,
        ])->save();

        $geofence = Geofence::factory()
            ->forGroup($group)
            ->create(['name' => 'Port Boundary']);

        $geofence->trackerDevices()->attach($tracker->id, ['tenant_id' => $tenant->id]);

        AlertEvent::factory()
            ->forLocationEvent($event)
            ->create([
                'geofence_id' => $geofence->id,
                'type' => 'geofence_entrance',
            ]);

        $plan = LicensePlan::factory()->create([
            'name' => 'Fleet Starter',
            'slug' => 'fleet-starter',
            'included_device_count' => 10,
        ]);

        LicenseAllocation::factory()->create([
            'tenant_id' => $tenant->id,
            'license_plan_id' => $plan->id,
            'active_device_count' => 1,
        ]);

        $licenseRequest = LicenseRequest::factory()
            ->requestedBy($user)
            ->create([
                'license_plan_id' => $plan->id,
                'request_type' => 'add',
                'requested_device_count' => 5,
            ]);

        PaymentSlip::factory()
            ->forRequest($licenseRequest, $user)
            ->create();
    }
}
