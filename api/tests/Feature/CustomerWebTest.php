<?php

namespace Tests\Feature;

use App\Models\FleetGroup;
use App\Models\Geofence;
use App\Models\LicensePlan;
use App\Models\LicenseRequest;
use App\Models\NormalizedLocationEvent;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_portal_with_tenant_scoped_data(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $tracker = TrackerDevice::factory()->for($tenant)->create(['display_name' => 'Visible Van']);
        TrackerDevice::factory()->for($otherTenant)->create(['display_name' => 'Hidden Van']);
        $event = NormalizedLocationEvent::factory()->forTracker($tracker)->create([
            'latitude' => 24.8607,
            'longitude' => 67.0011,
            'speed' => 32,
        ]);
        $tracker->forceFill(['last_event_id' => $event->id])->save();

        $this->actingAs($user)
            ->get('/customer/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Customer/Portal', false)
                ->where('activeModule', 'dashboard')
                ->has('trackers', 1)
                ->where('trackers.0.display_name', 'Visible Van'));
    }

    public function test_hidden_customer_module_is_forbidden_and_not_in_module_tabs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant, [
            'modules' => [
                'dashboard' => 'view',
                'billing' => 'hide',
            ],
        ]);

        $this->actingAs($user)
            ->get('/customer/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('allowedModules', ['dashboard'])
                ->has('modules', 1));

        $this->actingAs($user)
            ->get('/customer/billing')
            ->assertForbidden();
    }

    public function test_scoped_fleet_permission_can_open_my_fleets_without_module_wide_access(): void
    {
        $tenant = Tenant::factory()->create();
        $group = FleetGroup::factory()->for($tenant)->create();
        $tracker = TrackerDevice::factory()->for($tenant)->create();
        $group->trackerDevices()->attach($tracker, ['tenant_id' => $tenant->id]);
        $user = $this->tenantUser($tenant, [
            'modules' => [
                'my_fleets' => 'hide',
            ],
            'fleet_groups' => [
                (string) $group->id => [
                    'my_fleets' => 'view',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get('/customer/my-fleets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeModule', 'my_fleets')
                ->has('fleetGroups', 1)
                ->has('trackers', 1));
    }

    public function test_customer_can_create_license_request_and_payment_slip(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant, ['modules' => ['billing' => 'edit']]);
        $plan = LicensePlan::factory()->create(['price_amount' => 25, 'currency' => 'USD']);

        $this->actingAs($user)
            ->post('/customer/billing/license-requests', [
                'license_plan_id' => $plan->id,
                'request_type' => 'add',
                'requested_device_count' => 3,
            ])
            ->assertRedirect();

        $licenseRequest = LicenseRequest::query()->firstOrFail();

        $this->assertDatabaseHas('license_requests', [
            'tenant_id' => $tenant->id,
            'requested_by_user_id' => $user->id,
            'requested_device_count' => 3,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post('/customer/billing/payment-slips', [
                'license_request_id' => $licenseRequest->id,
                'original_filename' => 'bank-slip.pdf',
                'amount' => 75,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_slips', [
            'tenant_id' => $tenant->id,
            'license_request_id' => $licenseRequest->id,
            'uploaded_by_user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'payment_slip.uploaded',
        ]);
    }

    public function test_customer_can_create_geofence_with_audit_when_permitted(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant, ['modules' => ['geofence' => 'edit']]);

        $this->actingAs($user)
            ->post('/customer/geofence', [
                'name' => 'Depot',
                'shape_type' => 'circle',
                'speed_limit' => 55,
                'entrance_alert_enabled' => true,
                'exit_alert_enabled' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('geofences', [
            'tenant_id' => $tenant->id,
            'name' => 'Depot',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'geofence.created',
            'subject_type' => Geofence::class,
        ]);
    }

    public function test_customer_route_csv_export_respects_configured_columns_and_audits(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant, ['modules' => ['routes' => 'view']]);
        $tracker = TrackerDevice::factory()->for($tenant)->create();
        NormalizedLocationEvent::factory()->forTracker($tracker)->create(['speed' => 44.5]);

        $response = $this->actingAs($user)
            ->get('/customer/exports/routes?columns=tracker,speed')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('tracker,speed', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'report.exported',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $permissions
     */
    private function tenantUser(Tenant $tenant, ?array $permissions = null): User
    {
        $permissions ??= [
            'modules' => collect(config('mtrack.modules.customer'))
                ->mapWithKeys(fn (string $module): array => [$module => 'edit'])
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
