<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlertEvent;
use App\Models\AuditLog;
use App\Models\FleetGroup;
use App\Models\Geofence;
use App\Models\LicenseAllocation;
use App\Models\LicenseRequest;
use App\Models\NormalizedLocationEvent;
use App\Models\PaymentSlip;
use App\Models\RawPayload;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    private const MODULES = [
        'dashboard',
        'live',
        'playback',
        'events',
        'devices',
        'geofence',
        'routes',
        'customers',
        'payments',
        'logs',
    ];

    public function show(Request $request, string $module = 'dashboard'): Response
    {
        $this->authorizePlatformAdmin($request);
        abort_unless(in_array($module, self::MODULES, true), 404);

        return Inertia::render('Admin/Workspace', [
            'activeModule' => $module,
            'modules' => $this->moduleTabs(),
            ...$this->workspaceData(),
        ]);
    }

    public function approvePayment(Request $request, PaymentSlip $paymentSlip): RedirectResponse
    {
        $this->authorizePlatformAdmin($request);

        $paymentSlip->forceFill([
            'status' => 'approved',
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $paymentSlip->licenseRequest?->forceFill([
            'status' => 'approved',
            'reviewed_by_user_id' => $request->user()?->id,
            'rejection_reason' => null,
        ])->save();

        $this->audit($request, 'payment_slip.approved', $paymentSlip, $paymentSlip->tenant_id, [
            'amount' => $paymentSlip->amount,
            'license_request_id' => $paymentSlip->license_request_id,
        ]);

        return back()->with('status', 'Payment slip approved.');
    }

    public function rejectPayment(Request $request, PaymentSlip $paymentSlip): RedirectResponse
    {
        $this->authorizePlatformAdmin($request);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $paymentSlip->forceFill([
            'status' => 'rejected',
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ])->save();

        $paymentSlip->licenseRequest?->forceFill([
            'status' => 'rejected',
            'reviewed_by_user_id' => $request->user()?->id,
            'rejection_reason' => $validated['rejection_reason'],
        ])->save();

        $this->audit($request, 'payment_slip.rejected', $paymentSlip, $paymentSlip->tenant_id, [
            'license_request_id' => $paymentSlip->license_request_id,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('status', 'Payment slip rejected.');
    }

    public function approveCustomer(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizePlatformAdmin($request);

        $metadata = $tenant->metadata ?? [];
        $metadata['admin_acknowledged_at'] = now()->toIso8601String();
        $metadata['admin_acknowledged_by'] = $request->user()?->id;

        $tenant->forceFill([
            'status' => 'active',
            'metadata' => $metadata,
        ])->save();

        $this->audit($request, 'customer.approved', $tenant, $tenant->id);

        return back()->with('status', 'Customer approved.');
    }

    public function blockCustomer(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizePlatformAdmin($request);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $metadata = $tenant->metadata ?? [];
        $metadata['blocked_at'] = now()->toIso8601String();
        $metadata['blocked_by'] = $request->user()?->id;
        $metadata['block_reason'] = $validated['reason'] ?? null;

        $tenant->forceFill([
            'status' => 'blocked',
            'metadata' => $metadata,
        ])->save();

        $this->audit($request, 'customer.blocked', $tenant, $tenant->id, [
            'reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('status', 'Customer blocked.');
    }

    public function assignTracker(Request $request, TrackerDevice $trackerDevice): RedirectResponse
    {
        $this->authorizePlatformAdmin($request);

        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $previousTenantId = $trackerDevice->tenant_id;
        $metadata = $trackerDevice->metadata ?? [];
        $metadata['assigned_at'] = now()->toIso8601String();
        $metadata['assigned_by'] = $request->user()?->id;
        $metadata['previous_tenant_id'] = $previousTenantId;

        $trackerDevice->forceFill([
            'tenant_id' => $validated['tenant_id'],
            'display_name' => $validated['display_name'] ?: $trackerDevice->display_name,
            'metadata' => $metadata,
        ])->save();

        $this->audit($request, 'tracker_device.assigned', $trackerDevice, (int) $validated['tenant_id'], [
            'previous_tenant_id' => $previousTenantId,
        ]);

        return back()->with('status', 'Tracker assignment updated.');
    }

    public function assignDiscovered(Request $request): RedirectResponse
    {
        $this->authorizePlatformAdmin($request);

        $validated = $request->validate([
            'raw_payload_id' => ['required', 'exists:raw_payloads,id'],
            'tenant_id' => ['required', 'exists:tenants,id'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $rawPayload = RawPayload::withoutGlobalScope('tenant')->findOrFail($validated['raw_payload_id']);
        abort_if($rawPayload->tracker_device_id !== null, 409, 'Payload is already assigned to a tracker.');

        $identity = $this->payloadIdentity($rawPayload);
        $tracker = TrackerDevice::withoutGlobalScope('tenant')->create([
            'tenant_id' => $validated['tenant_id'],
            'display_name' => $validated['display_name'] ?: 'Discovered '.$identity,
            'business_label' => $identity,
            'contract_key' => $rawPayload->parser_contract_key,
            'contract_version' => $rawPayload->parser_contract_version,
            'status' => 'offline',
            'last_seen_at' => $rawPayload->received_at,
            'metadata' => [
                'device_identity' => $identity,
                'assigned_from_raw_payload_id' => $rawPayload->id,
                'assigned_at' => now()->toIso8601String(),
                'assigned_by' => $request->user()?->id,
            ],
        ]);

        $rawPayload->forceFill([
            'tenant_id' => $validated['tenant_id'],
            'tracker_device_id' => $tracker->id,
        ])->save();

        $this->audit($request, 'tracker_device.discovered_assigned', $tracker, (int) $validated['tenant_id'], [
            'raw_payload_id' => $rawPayload->id,
            'device_identity' => $identity,
        ]);

        return back()->with('status', 'Discovered device assigned.');
    }

    public function storeGeofence(Request $request): RedirectResponse
    {
        $this->authorizePlatformAdmin($request);

        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'shape_type' => ['required', 'in:circle,polygon'],
            'speed_limit' => ['nullable', 'numeric', 'min:0'],
            'entrance_alert_enabled' => ['required', 'boolean'],
            'exit_alert_enabled' => ['required', 'boolean'],
        ]);

        $geofence = Geofence::withoutGlobalScope('tenant')->create([
            'tenant_id' => $validated['tenant_id'],
            'name' => $validated['name'],
            'shape_type' => $validated['shape_type'],
            'shape_geometry' => $this->defaultGeometry($validated['shape_type']),
            'speed_limit' => $validated['speed_limit'] ?? null,
            'entrance_alert_enabled' => $request->boolean('entrance_alert_enabled'),
            'exit_alert_enabled' => $request->boolean('exit_alert_enabled'),
            'metadata' => [
                'created_from' => 'admin_web',
                'created_by' => $request->user()?->id,
            ],
        ]);

        $this->audit($request, 'geofence.created', $geofence, (int) $validated['tenant_id'], [
            'shape_type' => $validated['shape_type'],
        ]);

        return back()->with('status', 'Geofence saved.');
    }

    public function updateGeofence(Request $request, Geofence $geofence): RedirectResponse
    {
        $this->authorizePlatformAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'speed_limit' => ['nullable', 'numeric', 'min:0'],
            'entrance_alert_enabled' => ['required', 'boolean'],
            'exit_alert_enabled' => ['required', 'boolean'],
        ]);

        $geofence->forceFill([
            'name' => $validated['name'],
            'speed_limit' => $validated['speed_limit'] ?? null,
            'entrance_alert_enabled' => $request->boolean('entrance_alert_enabled'),
            'exit_alert_enabled' => $request->boolean('exit_alert_enabled'),
        ])->save();

        $this->audit($request, 'geofence.updated', $geofence, $geofence->tenant_id);

        return back()->with('status', 'Geofence updated.');
    }

    /**
     * @return array<int, array{id: string, label: string, count?: int}>
     */
    private function moduleTabs(): array
    {
        return [
            ['id' => 'dashboard', 'label' => 'Dashboard'],
            ['id' => 'live', 'label' => 'Live', 'count' => TrackerDevice::withoutGlobalScope('tenant')->count()],
            ['id' => 'playback', 'label' => 'Playback'],
            ['id' => 'events', 'label' => 'Events', 'count' => AlertEvent::withoutGlobalScope('tenant')->whereNull('resolved_at')->count()],
            ['id' => 'devices', 'label' => 'Devices', 'count' => RawPayload::withoutGlobalScope('tenant')->whereNull('tracker_device_id')->count()],
            ['id' => 'geofence', 'label' => 'Geofence'],
            ['id' => 'routes', 'label' => 'Routes'],
            ['id' => 'customers', 'label' => 'Customers', 'count' => Tenant::query()->count()],
            ['id' => 'payments', 'label' => 'Payments', 'count' => PaymentSlip::withoutGlobalScope('tenant')->where('status', 'pending')->count()],
            ['id' => 'logs', 'label' => 'Logs'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceData(): array
    {
        return [
            'dashboardMetrics' => $this->dashboardMetrics(),
            'liveStats' => $this->liveStats(),
            'trackers' => $this->trackerRows(),
            'customers' => $this->customerRows(),
            'payments' => $this->paymentRows(),
            'discoveredPayloads' => $this->discoveredPayloadRows(),
            'alertEvents' => $this->alertRows(),
            'geofences' => $this->geofenceRows(),
            'fleetGroups' => $this->fleetGroupRows(),
            'routeEvents' => $this->routeEventRows(),
            'rawPayloads' => $this->rawPayloadRows(),
            'auditLogs' => $this->auditRows(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dashboardMetrics(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return [
            [
                'label' => 'Total customers',
                'value' => Tenant::query()->count(),
                'helper' => 'All organizations on the platform',
                'icon' => 'Building2',
                'tone' => 'brand',
            ],
            [
                'label' => 'Expired this month',
                'value' => LicenseAllocation::withoutGlobalScope('tenant')
                    ->whereBetween('expires_at', [$monthStart, $monthEnd])
                    ->where('expires_at', '<', now())
                    ->count(),
                'helper' => 'License allocations needing renewal',
                'icon' => 'CalendarX',
                'tone' => 'danger',
            ],
            [
                'label' => 'Expiring soon',
                'value' => LicenseAllocation::withoutGlobalScope('tenant')
                    ->whereBetween('expires_at', [now(), now()->addDays(30)])
                    ->count(),
                'helper' => 'Allocations expiring in 30 days',
                'icon' => 'Clock',
                'tone' => 'warning',
            ],
            [
                'label' => 'New customers',
                'value' => Tenant::query()->whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                'helper' => 'Created during the current month',
                'icon' => 'UserPlus',
                'tone' => 'success',
            ],
            [
                'label' => 'Pending payments',
                'value' => PaymentSlip::withoutGlobalScope('tenant')->where('status', 'pending')->count(),
                'helper' => 'Manual slips awaiting review',
                'icon' => 'Receipt',
                'tone' => 'warning',
            ],
            [
                'label' => 'Total received',
                'value' => $this->money(PaymentSlip::withoutGlobalScope('tenant')
                    ->where('status', 'approved')
                    ->whereBetween('reviewed_at', [$monthStart, $monthEnd])
                    ->sum('amount')),
                'helper' => 'Approved slip value this month',
                'icon' => 'CreditCard',
                'tone' => 'success',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function liveStats(): array
    {
        $events = NormalizedLocationEvent::withoutGlobalScope('tenant')
            ->latest('event_timestamp')
            ->limit(500)
            ->get(['speed', 'normalized_metadata']);

        $distance = $events->sum(fn (NormalizedLocationEvent $event): float => (float) data_get($event->normalized_metadata, 'distance_km', 0));

        $batteryCount = TrackerDevice::withoutGlobalScope('tenant')
            ->get(['metadata'])
            ->filter(fn (TrackerDevice $tracker): bool => data_get($tracker->metadata, 'power_source') === 'battery')
            ->count();

        return [
            ['label' => 'Idle', 'value' => TrackerDevice::withoutGlobalScope('tenant')->where('status', 'idle')->count(), 'helper' => 'Currently idle trackers', 'icon' => 'PauseCircle', 'tone' => 'neutral'],
            ['label' => 'Moving', 'value' => TrackerDevice::withoutGlobalScope('tenant')->where('status', 'moving')->count(), 'helper' => 'Trackers reporting movement', 'icon' => 'Navigation', 'tone' => 'success'],
            ['label' => 'Offline', 'value' => TrackerDevice::withoutGlobalScope('tenant')->where('status', 'offline')->count(), 'helper' => 'No recent heartbeat', 'icon' => 'WifiOff', 'tone' => 'danger'],
            ['label' => 'On battery', 'value' => $batteryCount, 'helper' => 'Battery-powered trackers', 'icon' => 'Battery', 'tone' => 'warning'],
            ['label' => 'Total fleets', 'value' => FleetGroup::withoutGlobalScope('tenant')->count(), 'helper' => 'Fleet groups across customers', 'icon' => 'Layers', 'tone' => 'brand'],
            ['label' => 'Average speed', 'value' => round((float) NormalizedLocationEvent::withoutGlobalScope('tenant')->avg('speed'), 1).' km/h', 'helper' => 'Across normalized events', 'icon' => 'Gauge', 'tone' => 'info'],
            ['label' => 'Speed violations', 'value' => AlertEvent::withoutGlobalScope('tenant')->where('type', 'overspeed')->whereNull('resolved_at')->count(), 'helper' => 'Open overspeed alerts', 'icon' => 'Siren', 'tone' => 'danger'],
            ['label' => 'Total distance', 'value' => round($distance, 1).' km', 'helper' => 'Recent route metadata total', 'icon' => 'Route', 'tone' => 'brand'],
            ['label' => 'Max speed', 'value' => round((float) NormalizedLocationEvent::withoutGlobalScope('tenant')->max('speed'), 1).' km/h', 'helper' => 'Highest normalized speed', 'icon' => 'TrendingUp', 'tone' => 'warning'],
            ['label' => 'Trips', 'value' => NormalizedLocationEvent::withoutGlobalScope('tenant')->where('event_timestamp', '>=', now()->startOfDay())->distinct('tracker_device_id')->count('tracker_device_id'), 'helper' => 'Trackers active today', 'icon' => 'MapPinned', 'tone' => 'success'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trackerRows(): array
    {
        return TrackerDevice::withoutGlobalScope('tenant')
            ->with(['tenant:id,name,status,billing_status', 'lastEvent:id,tracker_device_id,latitude,longitude,speed,event_timestamp,status_metadata'])
            ->latest('last_seen_at')
            ->limit(40)
            ->get()
            ->map(fn (TrackerDevice $tracker): array => [
                'id' => $tracker->id,
                'display_name' => $tracker->display_name,
                'business_label' => $tracker->business_label,
                'tenant_id' => $tracker->tenant_id,
                'customer' => $tracker->tenant?->name ?? 'Unassigned',
                'status' => $tracker->status,
                'contract' => trim(($tracker->contract_key ?? 'no-contract').' v'.($tracker->contract_version ?? '-')),
                'last_seen_at' => $this->date($tracker->last_seen_at),
                'latitude' => $tracker->lastEvent?->latitude === null ? null : (float) $tracker->lastEvent->latitude,
                'longitude' => $tracker->lastEvent?->longitude === null ? null : (float) $tracker->lastEvent->longitude,
                'speed' => $tracker->lastEvent?->speed === null ? null : (float) $tracker->lastEvent->speed,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customerRows(): array
    {
        return Tenant::query()
            ->withCount(['users', 'trackerDevices', 'licenseRequests', 'paymentSlips'])
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'billing_status' => $tenant->billing_status,
                'users_count' => $tenant->users_count,
                'trackers_count' => $tenant->tracker_devices_count,
                'license_requests_count' => $tenant->license_requests_count,
                'payment_slips_count' => $tenant->payment_slips_count,
                'created_at' => $this->date($tenant->created_at),
                'acknowledged_at' => data_get($tenant->metadata, 'admin_acknowledged_at'),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paymentRows(): array
    {
        return PaymentSlip::withoutGlobalScope('tenant')
            ->with(['tenant:id,name,status,billing_status', 'licenseRequest:id,tenant_id,request_type,requested_device_count,status,currency'])
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (PaymentSlip $slip): array => [
                'id' => $slip->id,
                'customer' => $slip->tenant?->name ?? 'Unknown',
                'status' => $slip->status,
                'amount' => $this->money($slip->amount, $slip->licenseRequest?->currency ?? 'USD'),
                'filename' => $slip->original_filename ?? basename($slip->file_path),
                'request' => $slip->licenseRequest?->request_type ?? 'license',
                'devices' => $slip->licenseRequest?->requested_device_count ?? 0,
                'reviewed_at' => $this->date($slip->reviewed_at),
                'rejection_reason' => $slip->rejection_reason,
                'created_at' => $this->date($slip->created_at),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function discoveredPayloadRows(): array
    {
        return RawPayload::withoutGlobalScope('tenant')
            ->whereNull('tracker_device_id')
            ->latest('received_at')
            ->limit(25)
            ->get()
            ->map(fn (RawPayload $payload): array => [
                'id' => $payload->id,
                'identity' => $this->payloadIdentity($payload),
                'contract' => trim(($payload->parser_contract_key ?? 'unknown').' v'.($payload->parser_contract_version ?? '-')),
                'status' => $payload->processing_status,
                'reason' => $payload->rejection_reason ?? data_get($payload->metadata, 'diagnostics.reason'),
                'received_at' => $this->date($payload->received_at),
                'body_preview' => Str::limit($payload->body_content, 140),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function alertRows(): array
    {
        return AlertEvent::withoutGlobalScope('tenant')
            ->with(['tenant:id,name,status,billing_status', 'trackerDevice:id,display_name,business_label', 'geofence:id,name'])
            ->latest('occurred_at')
            ->limit(40)
            ->get()
            ->map(fn (AlertEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'customer' => $event->tenant?->name ?? 'Unknown',
                'tracker' => $event->trackerDevice?->display_name ?? 'No tracker',
                'geofence' => $event->geofence?->name,
                'occurred_at' => $this->date($event->occurred_at),
                'resolved_at' => $this->date($event->resolved_at),
                'severity' => data_get($event->metadata, 'severity', $event->resolved_at ? 'resolved' : 'open'),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function geofenceRows(): array
    {
        return Geofence::withoutGlobalScope('tenant')
            ->with(['tenant:id,name,status,billing_status', 'fleetGroup:id,name'])
            ->withCount('trackerDevices')
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (Geofence $geofence): array => [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'customer' => $geofence->tenant?->name ?? 'Unknown',
                'fleet_group' => $geofence->fleetGroup?->name,
                'shape_type' => $geofence->shape_type,
                'speed_limit' => $geofence->speed_limit,
                'entrance_alert_enabled' => $geofence->entrance_alert_enabled,
                'exit_alert_enabled' => $geofence->exit_alert_enabled,
                'trackers_count' => $geofence->tracker_devices_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fleetGroupRows(): array
    {
        return FleetGroup::withoutGlobalScope('tenant')
            ->with('tenant:id,name,status,billing_status')
            ->withCount('trackerDevices')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (FleetGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'customer' => $group->tenant?->name ?? 'Unknown',
                'visibility' => $group->visibility,
                'trackers_count' => $group->tracker_devices_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routeEventRows(): array
    {
        return NormalizedLocationEvent::withoutGlobalScope('tenant')
            ->with(['tenant:id,name,status,billing_status', 'trackerDevice:id,display_name,business_label'])
            ->latest('event_timestamp')
            ->limit(60)
            ->get()
            ->map(fn (NormalizedLocationEvent $event): array => [
                'id' => $event->id,
                'customer' => $event->tenant?->name ?? 'Unknown',
                'tracker' => $event->trackerDevice?->display_name ?? 'No tracker',
                'event_timestamp' => $this->date($event->event_timestamp),
                'latitude' => (float) $event->latitude,
                'longitude' => (float) $event->longitude,
                'speed' => $event->speed === null ? null : (float) $event->speed,
                'heading' => $event->heading === null ? null : (float) $event->heading,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rawPayloadRows(): array
    {
        return RawPayload::withoutGlobalScope('tenant')
            ->with(['tenant:id,name,status,billing_status', 'trackerDevice:id,display_name,business_label'])
            ->latest('received_at')
            ->limit(60)
            ->get()
            ->map(fn (RawPayload $payload): array => [
                'id' => $payload->id,
                'customer' => $payload->tenant?->name ?? 'Unresolved',
                'tracker' => $payload->trackerDevice?->display_name,
                'identity' => $this->payloadIdentity($payload),
                'contract' => trim(($payload->parser_contract_key ?? 'unknown').' v'.($payload->parser_contract_version ?? '-')),
                'status' => $payload->processing_status,
                'reason' => $payload->rejection_reason,
                'received_at' => $this->date($payload->received_at),
                'body_preview' => Str::limit($payload->body_content, 160),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function auditRows(): array
    {
        return AuditLog::withoutGlobalScope('tenant')
            ->with(['tenant:id,name,status,billing_status'])
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (AuditLog $audit): array => [
                'id' => $audit->id,
                'action' => $audit->action,
                'tenant' => $audit->tenant?->name ?? 'Platform',
                'actor_id' => $audit->actor_id,
                'subject_type' => class_basename($audit->subject_type),
                'subject_id' => $audit->subject_id,
                'created_at' => $this->date($audit->created_at),
                'metadata' => $audit->metadata ?? [],
            ])
            ->all();
    }

    private function authorizePlatformAdmin(Request $request): void
    {
        abort_unless($request->user()?->loadMissing('roles')->isPlatformAdmin(), 403);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(Request $request, string $action, Model $subject, ?int $tenantId = null, array $metadata = []): void
    {
        AuditLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantId,
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }

    private function payloadIdentity(RawPayload $payload): string
    {
        $metadata = $payload->metadata ?? [];

        return (string) (
            data_get($metadata, 'device_identity')
            ?? data_get($metadata, 'diagnostics.device_identity')
            ?? data_get($metadata, 'diagnostics.identity')
            ?? data_get($metadata, 'identity')
            ?? 'payload-'.$payload->id
        );
    }

    private function money(mixed $amount, string $currency = 'USD'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultGeometry(string $shapeType): array
    {
        if ($shapeType === 'polygon') {
            return [
                'points' => [
                    ['lat' => 24.8580, 'lng' => 67.0000],
                    ['lat' => 24.8650, 'lng' => 67.0020],
                    ['lat' => 24.8620, 'lng' => 67.0100],
                ],
            ];
        }

        return [
            'center' => ['lat' => 24.8607, 'lng' => 67.0011],
            'radius_m' => 500,
        ];
    }

    private function date(mixed $date): ?string
    {
        if ($date instanceof Carbon) {
            return $date->toIso8601String();
        }

        return $date ? Carbon::parse($date)->toIso8601String() : null;
    }
}
