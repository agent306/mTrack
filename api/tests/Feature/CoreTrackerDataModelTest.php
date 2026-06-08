<?php

namespace Tests\Feature;

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
use App\Models\TrackerDevice;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CoreTrackerDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_domain_records_are_scoped_to_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $tracker = TrackerDevice::factory()->create(['tenant_id' => $tenant->id]);
        TrackerDevice::factory()->create(['tenant_id' => $otherTenant->id]);

        app(CurrentTenant::class)->set($tenant);

        $this->assertSame([$tracker->id], TrackerDevice::query()->pluck('id')->all());
    }

    public function test_tracker_can_be_grouped_normalized_geofenced_and_alerted(): void
    {
        $tenant = Tenant::factory()->create();
        $group = FleetGroup::factory()->create(['tenant_id' => $tenant->id]);
        $tracker = TrackerDevice::factory()->create(['tenant_id' => $tenant->id]);

        $group->trackerDevices()->attach($tracker->id, ['tenant_id' => $tenant->id]);

        $rawPayload = RawPayload::factory()->forTracker($tracker)->create([
            'processing_status' => 'normalized',
        ]);

        $event = NormalizedLocationEvent::factory()
            ->forTracker($tracker, $rawPayload)
            ->create([
                'latitude' => 24.8607,
                'longitude' => 67.0011,
            ]);

        $tracker->forceFill([
            'last_event_id' => $event->id,
            'last_seen_at' => $event->event_timestamp,
        ])->save();

        $geofence = Geofence::factory()->forGroup($group)->create();
        $geofence->trackerDevices()->attach($tracker->id, ['tenant_id' => $tenant->id]);

        $alert = AlertEvent::factory()
            ->forLocationEvent($event)
            ->create([
                'geofence_id' => $geofence->id,
                'type' => 'geofence_entrance',
            ]);

        $this->assertTrue($tracker->fleetGroups()->whereKey($group->id)->exists());
        $this->assertSame($rawPayload->id, $event->rawPayload->id);
        $this->assertSame($event->id, $tracker->refresh()->lastEvent->id);
        $this->assertTrue($geofence->trackerDevices()->whereKey($tracker->id)->exists());
        $this->assertSame($alert->id, $event->alertEvents()->first()->id);
    }

    public function test_billing_license_request_and_payment_slip_are_tenant_bound(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $plan = LicensePlan::factory()->create();
        $allocation = LicenseAllocation::factory()->create([
            'tenant_id' => $tenant->id,
            'license_plan_id' => $plan->id,
        ]);
        $request = LicenseRequest::factory()
            ->requestedBy($user)
            ->create(['license_plan_id' => $plan->id]);
        $slip = PaymentSlip::factory()->forRequest($request, $user)->create();

        $this->assertSame($plan->id, $allocation->licensePlan->id);
        $this->assertSame($tenant->id, $request->tenant_id);
        $this->assertSame($user->id, $request->requestedBy->id);
        $this->assertSame($request->id, $slip->licenseRequest->id);
        $this->assertSame($tenant->id, $slip->tenant_id);
    }

    public function test_domain_policies_prevent_cross_tenant_access_and_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant, [
            'modules' => [
                'my_fleets' => 'view',
                'billing' => 'hide',
            ],
        ]);
        $tracker = TrackerDevice::factory()->create(['tenant_id' => $tenant->id]);
        $otherTracker = TrackerDevice::factory()->create(['tenant_id' => $otherTenant->id]);
        $licenseRequest = LicenseRequest::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $tracker));
        $this->assertTrue(Gate::forUser($user)->denies('update', $tracker));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherTracker));
        $this->assertTrue(Gate::forUser($user)->denies('view', $licenseRequest));
    }

    public function test_platform_admin_can_manage_global_license_plans(): void
    {
        $platformAdmin = User::factory()->create(['tenant_id' => null]);
        $platformRole = Role::factory()->create([
            'tenant_id' => null,
            'scope' => 'platform',
            'slug' => 'platform-admin',
        ]);
        $platformAdmin->roles()->attach($platformRole);
        $plan = LicensePlan::factory()->create();

        $this->assertTrue(Gate::forUser($platformAdmin->load('roles'))->allows('update', $plan));
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function tenantUser(Tenant $tenant, array $permissions): User
    {
        $role = Role::factory()->create([
            'tenant_id' => $tenant->id,
            'permissions' => $permissions,
        ]);

        $user = User::factory()->for($tenant)->create();
        $user->roles()->attach($role);

        return $user->load('roles', 'tenant');
    }
}
