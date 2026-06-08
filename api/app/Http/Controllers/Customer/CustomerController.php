<?php

namespace App\Http\Controllers\Customer;

use App\Billing\BillingManager;
use App\Http\Controllers\Controller;
use App\Models\AlertEvent;
use App\Models\AuditLog;
use App\Models\FleetGroup;
use App\Models\Geofence;
use App\Models\LicenseAllocation;
use App\Models\LicensePlan;
use App\Models\LicenseRequest;
use App\Models\NormalizedLocationEvent;
use App\Models\PaymentSlip;
use App\Models\RawPayload;
use App\Models\Role;
use App\Models\TenantSetting;
use App\Models\TrackerDevice;
use App\Models\User;
use App\Reports\ReportExportService;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\PermissionMatrix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    private const MODULES = [
        'dashboard' => ['label' => 'Dashboard', 'slug' => 'dashboard'],
        'live' => ['label' => 'Live', 'slug' => 'live'],
        'playback' => ['label' => 'Playback', 'slug' => 'playback'],
        'events' => ['label' => 'Events', 'slug' => 'events'],
        'my_fleets' => ['label' => 'My Fleets', 'slug' => 'my-fleets'],
        'geofence' => ['label' => 'Geofence', 'slug' => 'geofence'],
        'analysis' => ['label' => 'Analysis', 'slug' => 'analysis'],
        'routes' => ['label' => 'Routes', 'slug' => 'routes'],
        'settings' => ['label' => 'Setting', 'slug' => 'setting'],
        'billing' => ['label' => 'Billing', 'slug' => 'billing'],
        'audit_log' => ['label' => 'Audit Log', 'slug' => 'audit-log'],
    ];

    private const EXPORT_COLUMNS = ReportExportService::COLUMNS;

    public function show(Request $request, string $module = 'dashboard'): Response
    {
        $module = $this->normalizeModule($module);
        $this->authorizeTenantUser($request);
        $this->authorizeView($request, $module);

        return Inertia::render('Customer/Portal', [
            'activeModule' => $module,
            'modules' => $this->moduleTabs($request),
            'allowedModules' => $this->allowedModules($request),
            'permissions' => $this->permissions($request),
            ...$this->portalData($request),
        ]);
    }

    public function storeFleetGroup(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request, 'my_fleets');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', 'in:public,private'],
        ]);

        $group = FleetGroup::query()->create([
            'tenant_id' => $request->user()?->tenant_id,
            'name' => $validated['name'],
            'visibility' => $validated['visibility'],
            'metadata' => ['created_from' => 'customer_web'],
        ]);

        $this->audit($request, 'fleet_group.created', $group, ['visibility' => $validated['visibility']]);

        return back()->with('status', 'Fleet group created.');
    }

    public function updateTracker(Request $request, TrackerDevice $trackerDevice): RedirectResponse
    {
        $this->authorizeTrackerEdit($request, $trackerDevice, 'my_fleets');

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'business_label' => ['nullable', 'string', 'max:255'],
        ]);

        $trackerDevice->forceFill($validated)->save();
        $this->audit($request, 'tracker_device.updated', $trackerDevice, $validated);

        return back()->with('status', 'Tracker updated.');
    }

    public function storeGeofence(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request, 'geofence');

        $validated = $request->validate([
            'fleet_group_id' => ['nullable', 'exists:fleet_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'shape_type' => ['required', 'in:circle,polygon'],
            'speed_limit' => ['nullable', 'numeric', 'min:0'],
            'entrance_alert_enabled' => ['required', 'boolean'],
            'exit_alert_enabled' => ['required', 'boolean'],
        ]);

        $geofence = Geofence::query()->create([
            'tenant_id' => $request->user()?->tenant_id,
            'fleet_group_id' => $validated['fleet_group_id'] ?? null,
            'name' => $validated['name'],
            'shape_type' => $validated['shape_type'],
            'shape_geometry' => $this->defaultGeometry($validated['shape_type']),
            'speed_limit' => $validated['speed_limit'] ?? null,
            'entrance_alert_enabled' => $request->boolean('entrance_alert_enabled'),
            'exit_alert_enabled' => $request->boolean('exit_alert_enabled'),
            'metadata' => ['created_from' => 'customer_web'],
        ]);

        $this->audit($request, 'geofence.created', $geofence, ['shape_type' => $geofence->shape_type]);

        return back()->with('status', 'Geofence saved.');
    }

    public function updateGeofence(Request $request, Geofence $geofence): RedirectResponse
    {
        $this->authorizeEdit($request, 'geofence');
        abort_unless($geofence->tenant_id === $request->user()?->tenant_id, 403);

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

        $this->audit($request, 'geofence.updated', $geofence, $validated);

        return back()->with('status', 'Geofence updated.');
    }

    public function storeLicenseRequest(Request $request, BillingManager $billing): RedirectResponse
    {
        $this->authorizeEdit($request, 'billing');

        $validated = $request->validate([
            'license_plan_id' => ['required', 'exists:license_plans,id'],
            'request_type' => ['required', 'in:add,renew'],
            'requested_device_count' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $plan = LicensePlan::query()->findOrFail($validated['license_plan_id']);
        $billing->createLicenseRequest(
            $request->user(),
            $plan,
            $validated['request_type'],
            (int) $validated['requested_device_count'],
            $request,
        );

        return back()->with('status', 'License request created. Upload a payment slip to proceed.');
    }

    public function uploadPaymentSlip(Request $request, BillingManager $billing): RedirectResponse
    {
        $this->authorizeEdit($request, 'billing');

        $validated = $request->validate([
            'license_request_id' => ['required', 'exists:license_requests,id'],
            'original_filename' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $licenseRequest = LicenseRequest::query()->findOrFail($validated['license_request_id']);
        abort_unless($licenseRequest->tenant_id === $request->user()?->tenant_id, 403);

        $billing->uploadPaymentSlip(
            $request->user(),
            $licenseRequest,
            $validated['original_filename'],
            isset($validated['amount']) ? (float) $validated['amount'] : null,
            $request,
        );

        return back()->with('status', 'Payment slip submitted for approval.');
    }

    public function storeSetting(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request, 'settings');

        $validated = $request->validate([
            'dashboard_density' => ['required', 'in:compact,comfortable'],
            'default_map_zoom' => ['required', 'integer', 'min:4', 'max:18'],
            'raw_payload_retention_days' => ['required', 'integer', 'in:30,90,180,365'],
        ]);

        TenantSetting::query()->updateOrCreate(
            ['tenant_id' => $request->user()?->tenant_id, 'key' => 'dashboard.preferences'],
            ['value' => [
                'dashboard_density' => $validated['dashboard_density'],
                'default_map_zoom' => $validated['default_map_zoom'],
                'raw_payload_retention_days' => $validated['raw_payload_retention_days'],
            ]]
        );

        $request->user()?->tenant?->forceFill([
            'raw_payload_retention_days' => $validated['raw_payload_retention_days'],
        ])->save();

        $this->audit($request, 'tenant_setting.updated', $request->user()?->tenant, $validated);

        return back()->with('status', 'Settings saved.');
    }

    public function regenerateApiToken(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request, 'settings');

        $plainToken = 'mtk_'.Str::random(40);

        TenantSetting::query()->updateOrCreate(
            ['tenant_id' => $request->user()?->tenant_id, 'key' => 'api.token'],
            ['value' => [
                'token_hash' => hash('sha256', $plainToken),
                'token_preview' => substr($plainToken, 0, 10).'...',
                'rotated_at' => now()->toIso8601String(),
                'rotated_by' => $request->user()?->id,
            ]]
        );

        $this->audit($request, 'api_token.regenerated', $request->user()?->tenant);

        return back()->with('status', 'API token regenerated. Copy the new token from the secure operator workflow.');
    }

    public function exportCsv(Request $request, string $report, ReportExportService $exports, AuditLogger $audit): StreamedResponse
    {
        $report = $exports->normalize($report);
        $this->authorizeView($request, $exports->moduleFor($report));

        $columns = $exports->columns($report, $request->query('columns', []));
        $rows = $exports->rows($report, $request->user()?->tenant_id);

        $audit->record($request->user(), 'report.exported', $request->user()?->tenant, $request->user()?->tenant_id, [
            'report' => $report,
            'columns' => $columns,
            'scope' => 'tenant',
        ], $request);

        return ResponseFactory::streamDownload(function () use ($columns, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, collect($columns)->map(fn (string $column): mixed => $row[$column] ?? null)->all());
            }

            fclose($handle);
        }, 'mtrack-'.$report.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function portalData(Request $request): array
    {
        $trackers = $this->visibleTrackers($request);
        $routeEvents = $this->routeEventRows($trackers->pluck('id')->all());

        return [
            'dashboardMetrics' => $this->dashboardMetrics($trackers),
            'trackers' => $this->trackerRows($request, $trackers),
            'fleetGroups' => $this->fleetGroupRows($request),
            'geofences' => $this->geofenceRows($request),
            'alertEvents' => $this->alertRows($trackers),
            'routeEvents' => $routeEvents,
            'tripAnalytics' => $this->tripAnalytics($routeEvents),
            'rawPayloads' => $this->rawPayloadRows($trackers),
            'auditLogs' => $this->auditRows(),
            'licenseAllocations' => $this->licenseAllocationRows(),
            'licenseRequests' => $this->licenseRequestRows(),
            'paymentSlips' => $this->paymentRows(),
            'licensePlans' => $this->licensePlanRows(),
            'users' => $this->userRows(),
            'roles' => $this->roleRows(),
            'settings' => $this->settingRows($request),
            'exportColumns' => self::EXPORT_COLUMNS,
        ];
    }

    private function dashboardMetrics($trackers): array
    {
        $trackerIds = $trackers->pluck('id')->all();
        $events = NormalizedLocationEvent::query()
            ->whereIn('tracker_device_id', $trackerIds)
            ->latest('event_timestamp')
            ->limit(500)
            ->get();

        return [
            ['label' => 'Fleet status', 'value' => $trackers->where('status', 'moving')->count().' moving', 'helper' => $trackers->count().' visible trackers', 'icon' => 'RadioTower', 'tone' => 'brand'],
            ['label' => 'Geofence stats', 'value' => Geofence::query()->count(), 'helper' => 'Configured areas and alert rules', 'icon' => 'Map', 'tone' => 'success'],
            ['label' => 'Moving hours', 'value' => round($events->where('speed', '>', 1)->count() * 0.1, 1).' h', 'helper' => 'Derived from recent event cadence', 'icon' => 'Clock', 'tone' => 'info'],
            ['label' => 'Average speed', 'value' => round((float) $events->avg('speed'), 1).' km/h', 'helper' => 'Recent normalized speed average', 'icon' => 'Gauge', 'tone' => 'warning'],
            ['label' => 'Open events', 'value' => AlertEvent::query()->whereIn('tracker_device_id', $trackerIds)->whereNull('resolved_at')->count(), 'helper' => 'Geofence, overspeed, and device alerts', 'icon' => 'Bell', 'tone' => 'danger'],
            ['label' => 'Ingestion health', 'value' => RawPayload::query()->whereIn('tracker_device_id', $trackerIds)->where('processing_status', 'normalized')->count(), 'helper' => 'Normalized payloads in tenant logs', 'icon' => 'Activity', 'tone' => 'success'],
        ];
    }

    private function trackerRows(Request $request, $trackers): array
    {
        return $trackers
            ->map(fn (TrackerDevice $tracker): array => [
                'id' => $tracker->id,
                'display_name' => $tracker->display_name,
                'business_label' => $tracker->business_label,
                'status' => $tracker->status,
                'contract' => trim(($tracker->contract_key ?? 'no-contract').' v'.($tracker->contract_version ?? '-')),
                'last_seen_at' => $this->date($tracker->last_seen_at),
                'latitude' => $tracker->lastEvent?->latitude === null ? null : (float) $tracker->lastEvent->latitude,
                'longitude' => $tracker->lastEvent?->longitude === null ? null : (float) $tracker->lastEvent->longitude,
                'speed' => $tracker->lastEvent?->speed === null ? null : (float) $tracker->lastEvent->speed,
                'groups' => $tracker->fleetGroups->map(fn (FleetGroup $group): array => ['id' => $group->id, 'name' => $group->name])->all(),
                'can_edit' => $this->canEditTracker($request, $tracker, 'my_fleets'),
            ])
            ->values()
            ->all();
    }

    private function fleetGroupRows(Request $request): array
    {
        return FleetGroup::query()
            ->withCount('trackerDevices')
            ->latest()
            ->get()
            ->filter(fn (FleetGroup $group): bool => $this->canView($request, 'my_fleets', $group->id))
            ->map(fn (FleetGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'visibility' => $group->visibility,
                'trackers_count' => $group->tracker_devices_count,
            ])
            ->values()
            ->all();
    }

    private function geofenceRows(Request $request): array
    {
        return Geofence::query()
            ->with('fleetGroup:id,name')
            ->withCount('trackerDevices')
            ->latest()
            ->get()
            ->filter(fn (Geofence $geofence): bool => $this->canView($request, 'geofence', $geofence->fleet_group_id))
            ->map(fn (Geofence $geofence): array => [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'fleet_group_id' => $geofence->fleet_group_id,
                'fleet_group' => $geofence->fleetGroup?->name,
                'shape_type' => $geofence->shape_type,
                'speed_limit' => $geofence->speed_limit,
                'entrance_alert_enabled' => $geofence->entrance_alert_enabled,
                'exit_alert_enabled' => $geofence->exit_alert_enabled,
                'trackers_count' => $geofence->tracker_devices_count,
            ])
            ->values()
            ->all();
    }

    private function alertRows($trackers): array
    {
        return AlertEvent::query()
            ->with(['trackerDevice:id,display_name,business_label', 'geofence:id,name'])
            ->whereIn('tracker_device_id', $trackers->pluck('id')->all())
            ->latest('occurred_at')
            ->limit(60)
            ->get()
            ->map(fn (AlertEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'tracker' => $event->trackerDevice?->display_name ?? 'No tracker',
                'geofence' => $event->geofence?->name,
                'occurred_at' => $this->date($event->occurred_at),
                'resolved_at' => $this->date($event->resolved_at),
                'severity' => data_get($event->metadata, 'severity', $event->resolved_at ? 'resolved' : 'open'),
            ])
            ->all();
    }

    private function routeEventRows(array $trackerIds): array
    {
        return NormalizedLocationEvent::query()
            ->with('trackerDevice:id,display_name,business_label')
            ->whereIn('tracker_device_id', $trackerIds)
            ->latest('event_timestamp')
            ->limit(120)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (NormalizedLocationEvent $event): array => [
                'id' => $event->id,
                'tracker_id' => $event->tracker_device_id,
                'tracker' => $event->trackerDevice?->display_name ?? 'No tracker',
                'event_timestamp' => $this->date($event->event_timestamp),
                'latitude' => (float) $event->latitude,
                'longitude' => (float) $event->longitude,
                'speed' => $event->speed === null ? null : (float) $event->speed,
                'heading' => $event->heading === null ? null : (float) $event->heading,
                'distance_km' => (float) data_get($event->normalized_metadata, 'distance_km', 0),
            ])
            ->all();
    }

    private function rawPayloadRows($trackers): array
    {
        return RawPayload::query()
            ->with('trackerDevice:id,display_name,business_label')
            ->whereIn('tracker_device_id', $trackers->pluck('id')->all())
            ->latest('received_at')
            ->limit(80)
            ->get()
            ->map(fn (RawPayload $payload): array => [
                'id' => $payload->id,
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

    private function auditRows(): array
    {
        return AuditLog::query()
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (AuditLog $audit): array => [
                'id' => $audit->id,
                'action' => $audit->action,
                'actor_id' => $audit->actor_id,
                'subject_type' => class_basename($audit->subject_type),
                'subject_id' => $audit->subject_id,
                'created_at' => $this->date($audit->created_at),
                'metadata' => $audit->metadata ?? [],
            ])
            ->all();
    }

    private function licenseAllocationRows(): array
    {
        return LicenseAllocation::query()
            ->with('licensePlan:id,name,slug,currency')
            ->latest()
            ->get()
            ->map(fn (LicenseAllocation $allocation): array => [
                'id' => $allocation->id,
                'plan' => $allocation->licensePlan?->name ?? 'Custom',
                'active_device_count' => $allocation->active_device_count,
                'starts_at' => $this->date($allocation->starts_at),
                'expires_at' => $this->date($allocation->expires_at),
                'status' => $allocation->status,
            ])
            ->all();
    }

    private function licenseRequestRows(): array
    {
        return LicenseRequest::query()
            ->with('licensePlan:id,name,slug,currency')
            ->latest()
            ->get()
            ->map(fn (LicenseRequest $licenseRequest): array => [
                'id' => $licenseRequest->id,
                'plan' => $licenseRequest->licensePlan?->name ?? 'Custom',
                'request_type' => $licenseRequest->request_type,
                'requested_device_count' => $licenseRequest->requested_device_count,
                'amount' => $this->money($licenseRequest->amount, $licenseRequest->currency),
                'status' => $licenseRequest->status,
                'created_at' => $this->date($licenseRequest->created_at),
            ])
            ->all();
    }

    private function paymentRows(): array
    {
        return PaymentSlip::query()
            ->latest()
            ->get()
            ->map(fn (PaymentSlip $slip): array => [
                'id' => $slip->id,
                'license_request_id' => $slip->license_request_id,
                'filename' => $slip->original_filename ?? basename($slip->file_path),
                'amount' => $this->money($slip->amount),
                'status' => $slip->status,
                'rejection_reason' => $slip->rejection_reason,
                'reviewed_at' => $this->date($slip->reviewed_at),
            ])
            ->all();
    }

    private function licensePlanRows(): array
    {
        return LicensePlan::query()
            ->where('status', 'active')
            ->orderBy('price_amount')
            ->get()
            ->map(fn (LicensePlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'included_device_count' => $plan->included_device_count,
                'price_amount' => $this->money($plan->price_amount, $plan->currency),
                'currency' => $plan->currency,
            ])
            ->all();
    }

    private function userRows(): array
    {
        return User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->roles->pluck('name')->all(),
            ])
            ->all();
    }

    private function roleRows(): array
    {
        return Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions,
            ])
            ->all();
    }

    private function settingRows(Request $request): array
    {
        $preferences = TenantSetting::query()->where('key', 'dashboard.preferences')->first()?->value ?? [];
        $token = TenantSetting::query()->where('key', 'api.token')->first()?->value ?? [];

        return [
            'dashboard_density' => data_get($preferences, 'dashboard_density', 'compact'),
            'default_map_zoom' => data_get($preferences, 'default_map_zoom', 12),
            'raw_payload_retention_days' => $request->user()?->tenant?->raw_payload_retention_days ?? 90,
            'api_token_preview' => data_get($token, 'token_preview', 'Not generated'),
            'api_token_rotated_at' => data_get($token, 'rotated_at'),
        ];
    }

    private function tripAnalytics(array $events): array
    {
        $distance = 0.0;
        $previous = null;

        foreach ($events as $event) {
            $distance += $event['distance_km'] ?: ($previous ? $this->distanceKm($previous, $event) : 0);
            $previous = $event;
        }

        return [
            'summary' => [
                'events' => count($events),
                'distance_km' => round($distance, 2),
                'moving_time_minutes' => collect($events)->where('speed', '>', 1)->count() * 6,
                'idle_time_minutes' => collect($events)->filter(fn (array $event): bool => (float) ($event['speed'] ?? 0) <= 1)->count() * 6,
                'max_speed' => round((float) collect($events)->max('speed'), 1),
                'average_speed' => round((float) collect($events)->avg('speed'), 1),
            ],
            'stops' => collect($events)
                ->filter(fn (array $event): bool => (float) ($event['speed'] ?? 0) <= 1)
                ->take(12)
                ->values()
                ->all(),
            'segments' => $this->segments($events),
            'speedGraph' => collect($events)
                ->map(fn (array $event): array => ['label' => $event['event_timestamp'], 'speed' => $event['speed'] ?? 0])
                ->values()
                ->all(),
        ];
    }

    private function segments(array $events): array
    {
        $segments = [];
        $current = null;

        foreach ($events as $event) {
            $type = (float) ($event['speed'] ?? 0) > 1 ? 'moving' : 'idle';

            if ($current === null || $current['type'] !== $type) {
                if ($current !== null) {
                    $segments[] = $current;
                }

                $current = [
                    'type' => $type,
                    'start_at' => $event['event_timestamp'],
                    'end_at' => $event['event_timestamp'],
                    'events' => 1,
                ];

                continue;
            }

            $current['end_at'] = $event['event_timestamp'];
            $current['events']++;
        }

        if ($current !== null) {
            $segments[] = $current;
        }

        return $segments;
    }

    private function visibleTrackers(Request $request)
    {
        return TrackerDevice::query()
            ->with(['lastEvent:id,tracker_device_id,latitude,longitude,speed,event_timestamp,status_metadata', 'fleetGroups:id,name'])
            ->latest('last_seen_at')
            ->get()
            ->filter(function (TrackerDevice $tracker) use ($request): bool {
                $groupIds = $tracker->fleetGroups->pluck('id')->all();

                return $this->canView($request, 'live', null, $tracker->id)
                    || $this->canView($request, 'my_fleets', null, $tracker->id)
                    || collect($groupIds)->contains(fn (int $groupId): bool => $this->canView($request, 'live', $groupId) || $this->canView($request, 'my_fleets', $groupId));
            })
            ->values();
    }

    private function moduleTabs(Request $request): array
    {
        return collect(self::MODULES)
            ->filter(fn (array $module, string $id): bool => $this->canAccessModule($request, $id))
            ->map(fn (array $module, string $id): array => [
                'id' => $id,
                'label' => $module['label'],
                'slug' => $module['slug'],
                'count' => $this->moduleCount($id),
            ])
            ->values()
            ->all();
    }

    private function allowedModules(Request $request): array
    {
        return array_values(array_filter(array_keys(self::MODULES), fn (string $module): bool => $this->canAccessModule($request, $module)));
    }

    private function permissions(Request $request): array
    {
        return collect(self::MODULES)
            ->mapWithKeys(fn (array $module, string $id): array => [$id => $request->user()?->permissionLevel($id)])
            ->all();
    }

    private function moduleCount(string $module): int
    {
        return match ($module) {
            'live', 'my_fleets' => TrackerDevice::query()->count(),
            'events' => AlertEvent::query()->whereNull('resolved_at')->count(),
            'geofence' => Geofence::query()->count(),
            'billing' => LicenseRequest::query()->where('status', 'pending')->count(),
            'audit_log' => AuditLog::query()->count(),
            default => 0,
        };
    }

    private function authorizeTenantUser(Request $request): void
    {
        abort_unless($request->user()?->tenant_id !== null, 403);
    }

    private function authorizeView(Request $request, string $module): void
    {
        $this->authorizeTenantUser($request);
        abort_unless($this->canAccessModule($request, $module), 403);
    }

    private function authorizeEdit(Request $request, string $module): void
    {
        $this->authorizeTenantUser($request);
        abort_unless($request->user()?->hasPermission($module, PermissionMatrix::EDIT), 403);
    }

    private function authorizeTrackerEdit(Request $request, TrackerDevice $trackerDevice, string $module): void
    {
        $this->authorizeTenantUser($request);
        abort_unless($trackerDevice->tenant_id === $request->user()?->tenant_id, 403);
        abort_unless($this->canEditTracker($request, $trackerDevice, $module), 403);
    }

    private function canAccessModule(Request $request, string $module): bool
    {
        return $request->user()?->hasPermission($module, PermissionMatrix::VIEW)
            || $this->hasScopedPermission($request, $module, PermissionMatrix::VIEW);
    }

    private function canView(Request $request, string $module, ?int $fleetGroupId = null, ?int $trackerId = null): bool
    {
        return (bool) $request->user()?->hasPermission($module, PermissionMatrix::VIEW, $fleetGroupId, $trackerId);
    }

    private function canEditTracker(Request $request, TrackerDevice $trackerDevice, string $module): bool
    {
        if ($request->user()?->hasPermission($module, PermissionMatrix::EDIT, trackerId: $trackerDevice->id)) {
            return true;
        }

        return $trackerDevice->fleetGroups
            ->contains(fn (FleetGroup $group): bool => (bool) $request->user()?->hasPermission($module, PermissionMatrix::EDIT, fleetGroupId: $group->id));
    }

    private function hasScopedPermission(Request $request, string $module, string $required): bool
    {
        $user = $request->user()?->loadMissing('roles');

        if (! $user) {
            return false;
        }

        return $user->roles->contains(function (Role $role) use ($module, $required): bool {
            $permissions = $role->permissions ?? [];

            foreach (['fleet_groups', 'trackers'] as $scope) {
                $rules = $permissions[$scope] ?? [];

                if (! is_array($rules)) {
                    continue;
                }

                foreach ($rules as $rule) {
                    $level = is_string($rule) ? $rule : ($rule[$module] ?? $rule['*'] ?? PermissionMatrix::HIDE);

                    if (PermissionMatrix::allows((string) $level, $required)) {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    private function normalizeModule(string $module): string
    {
        $module = str_replace('-', '_', $module);
        abort_unless(array_key_exists($module, self::MODULES) || array_key_exists($module, self::EXPORT_COLUMNS), 404);

        return $module;
    }

    private function audit(Request $request, string $action, ?Model $subject, array $metadata = []): void
    {
        AuditLog::query()->create([
            'tenant_id' => $request->user()?->tenant_id,
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
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
            ?? data_get($metadata, 'identity')
            ?? 'payload-'.$payload->id
        );
    }

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

    private function distanceKm(array $a, array $b): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($b['latitude'] - $a['latitude']);
        $lngDelta = deg2rad($b['longitude'] - $a['longitude']);
        $haversine = sin($latDelta / 2) ** 2
            + cos(deg2rad($a['latitude'])) * cos(deg2rad($b['latitude'])) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }

    private function money(mixed $amount, string $currency = 'USD'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    private function date(mixed $date): ?string
    {
        if ($date instanceof Carbon) {
            return $date->toIso8601String();
        }

        return $date ? Carbon::parse($date)->toIso8601String() : null;
    }
}
