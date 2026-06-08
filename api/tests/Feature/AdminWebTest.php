<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Geofence;
use App\Models\LicenseAllocation;
use App\Models\LicenseRequest;
use App\Models\PaymentSlip;
use App\Models\RawPayload;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_admin_modules(): void
    {
        $admin = $this->platformAdmin();
        TrackerDevice::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Workspace', false)
                ->where('activeModule', 'dashboard')
                ->has('modules')
                ->has('trackers', 1));
    }

    public function test_tenant_user_cannot_view_admin_modules(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_platform_admin_can_approve_payment_slip_and_license_request(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();
        $licenseRequest = LicenseRequest::factory()->for($tenant)->create(['status' => 'pending']);
        $slip = PaymentSlip::factory()->forRequest($licenseRequest)->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->post("/admin/payments/{$slip->id}/approve")
            ->assertRedirect();

        $this->assertDatabaseHas('payment_slips', [
            'id' => $slip->id,
            'status' => 'approved',
            'reviewed_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('license_requests', [
            'id' => $licenseRequest->id,
            'status' => 'approved',
            'reviewed_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('license_allocations', [
            'tenant_id' => $tenant->id,
            'license_plan_id' => $licenseRequest->license_plan_id,
            'active_device_count' => $licenseRequest->requested_device_count,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'billing_status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id' => $admin->id,
            'action' => 'payment_slip.approved',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id' => $admin->id,
            'action' => 'license_allocation.activated',
        ]);
    }

    public function test_platform_admin_can_reject_payment_slip(): void
    {
        $admin = $this->platformAdmin();
        $licenseRequest = LicenseRequest::factory()->create(['status' => 'pending']);
        $slip = PaymentSlip::factory()->forRequest($licenseRequest)->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->post("/admin/payments/{$slip->id}/reject", ['rejection_reason' => 'Unreadable slip'])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_slips', [
            'id' => $slip->id,
            'status' => 'rejected',
            'rejection_reason' => 'Unreadable slip',
        ]);
        $this->assertDatabaseHas('license_requests', [
            'id' => $licenseRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'Unreadable slip',
        ]);
        $this->assertDatabaseMissing('license_allocations', [
            'tenant_id' => $licenseRequest->tenant_id,
            'license_plan_id' => $licenseRequest->license_plan_id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $licenseRequest->tenant_id,
            'actor_id' => $admin->id,
            'action' => 'license_request.rejected',
        ]);
    }

    public function test_platform_admin_can_renew_existing_license_allocation(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();
        $allocation = LicenseAllocation::factory()->for($tenant)->create([
            'active_device_count' => 5,
            'expires_at' => now()->addDays(10),
        ]);
        $licenseRequest = LicenseRequest::factory()->for($tenant)->create([
            'license_plan_id' => $allocation->license_plan_id,
            'request_type' => 'renew',
            'requested_device_count' => 3,
            'status' => 'pending',
        ]);
        $slip = PaymentSlip::factory()->forRequest($licenseRequest)->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->post("/admin/payments/{$slip->id}/approve")
            ->assertRedirect();

        $this->assertDatabaseCount('license_allocations', 1);
        $this->assertSame(5, $allocation->refresh()->active_device_count);
        $this->assertTrue($allocation->expires_at->greaterThan(now()->addYear()));
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id' => $admin->id,
            'action' => 'license_allocation.renewed',
        ]);
    }

    public function test_platform_admin_can_approve_and_block_customers(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->post("/admin/customers/{$tenant->id}/approve")
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'customer.approved']);

        $this->actingAs($admin)
            ->post("/admin/customers/{$tenant->id}/block", ['reason' => 'Non-payment'])
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'blocked']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'customer.blocked']);
    }

    public function test_platform_admin_can_assign_discovered_payload_to_customer(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();
        $payload = RawPayload::factory()->create([
            'tenant_id' => null,
            'tracker_device_id' => null,
            'metadata' => ['diagnostics' => ['device_identity' => 'imei-123']],
        ]);

        $this->actingAs($admin)
            ->post('/admin/devices/assign-discovered', [
                'raw_payload_id' => $payload->id,
                'tenant_id' => $tenant->id,
                'display_name' => 'Delivery Van 12',
            ])
            ->assertRedirect();

        $tracker = TrackerDevice::withoutGlobalScope('tenant')->where('business_label', 'imei-123')->first();

        $this->assertNotNull($tracker);
        $this->assertSame($tenant->id, $tracker->tenant_id);
        $this->assertDatabaseHas('raw_payloads', [
            'id' => $payload->id,
            'tenant_id' => $tenant->id,
            'tracker_device_id' => $tracker->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tracker_device.discovered_assigned',
        ]);
    }

    public function test_platform_admin_can_create_and_modify_geofence_with_audit(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/geofence', [
                'tenant_id' => $tenant->id,
                'name' => 'Depot Radius',
                'shape_type' => 'circle',
                'speed_limit' => 55,
                'entrance_alert_enabled' => true,
                'exit_alert_enabled' => true,
            ])
            ->assertRedirect();

        $geofence = Geofence::withoutGlobalScope('tenant')->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/geofence/{$geofence->id}", [
                'name' => 'Depot Updated',
                'speed_limit' => 45,
                'entrance_alert_enabled' => true,
                'exit_alert_enabled' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('geofences', [
            'id' => $geofence->id,
            'name' => 'Depot Updated',
            'exit_alert_enabled' => false,
        ]);
        $this->assertSame(2, AuditLog::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->whereIn('action', ['geofence.created', 'geofence.updated'])->count());
    }

    public function test_platform_admin_can_export_platform_reports_with_audit(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->get('/admin/exports/device-status?columns=tracker,status')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('tracker,status', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => null,
            'actor_id' => $admin->id,
            'action' => 'report.exported',
        ]);
    }

    private function platformAdmin(): User
    {
        $role = Role::factory()->create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'slug' => 'platform-admin',
            'scope' => 'platform',
            'permissions' => ['modules' => ['admin' => 'edit']],
        ]);

        $user = User::factory()->create(['tenant_id' => null]);
        $user->roles()->attach($role);

        return $user->load('roles');
    }
}
