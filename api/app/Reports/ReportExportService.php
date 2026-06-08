<?php

namespace App\Reports;

use App\Models\AlertEvent;
use App\Models\AuditLog;
use App\Models\Geofence;
use App\Models\NormalizedLocationEvent;
use App\Models\RawPayload;
use App\Models\TrackerDevice;
use Illuminate\Support\Carbon;

class ReportExportService
{
    public const COLUMNS = [
        'geofence' => ['name', 'fleet_group', 'shape_type', 'speed_limit', 'entrance_alert_enabled', 'exit_alert_enabled', 'trackers_count'],
        'overspeed' => ['tracker', 'occurred_at', 'resolved_at', 'severity', 'speed', 'speed_limit'],
        'events' => ['type', 'tracker', 'occurred_at', 'resolved_at', 'severity'],
        'device_status' => ['tracker', 'status', 'last_seen_at', 'latitude', 'longitude', 'speed', 'contract'],
        'routes' => ['tracker', 'event_timestamp', 'latitude', 'longitude', 'speed', 'heading', 'distance_km'],
        'logs' => ['received_at', 'tracker', 'identity', 'contract', 'status', 'reason'],
        'device_logs' => ['received_at', 'tracker', 'identity', 'contract', 'status', 'reason'],
        'analysis' => ['metric', 'value'],
        'audit_log' => ['created_at', 'actor_id', 'action', 'subject_type', 'subject_id'],
    ];

    public function normalize(string $report): string
    {
        $report = str_replace('-', '_', $report);
        abort_unless(array_key_exists($report, self::COLUMNS), 404);

        return $report;
    }

    public function moduleFor(string $report): string
    {
        return match ($this->normalize($report)) {
            'device_logs', 'logs' => 'settings',
            'audit_log' => 'audit_log',
            'overspeed', 'events' => 'events',
            'device_status' => 'live',
            default => $report,
        };
    }

    /**
     * @return array<int, string>
     */
    public function columns(string $report, mixed $requestedColumns = []): array
    {
        $report = $this->normalize($report);
        $requestedColumns = is_string($requestedColumns) ? explode(',', $requestedColumns) : $requestedColumns;

        $columns = collect($requestedColumns)
            ->filter(fn (mixed $column): bool => is_string($column) && in_array($column, self::COLUMNS[$report], true))
            ->values()
            ->all();

        return $columns ?: self::COLUMNS[$report];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $report, ?int $tenantId = null): array
    {
        return match ($this->normalize($report)) {
            'geofence' => $this->geofenceRows($tenantId),
            'overspeed' => $this->overspeedRows($tenantId),
            'events' => $this->eventRows($tenantId),
            'device_status' => $this->deviceStatusRows($tenantId),
            'routes' => $this->routeRows($tenantId),
            'logs', 'device_logs' => $this->rawPayloadRows($tenantId),
            'analysis' => $this->analysisRows($tenantId),
            'audit_log' => $this->auditRows($tenantId),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function geofenceRows(?int $tenantId): array
    {
        return Geofence::withoutGlobalScope('tenant')
            ->with('fleetGroup:id,name')
            ->withCount('trackerDevices')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest()
            ->limit(1000)
            ->get()
            ->map(fn (Geofence $geofence): array => [
                'name' => $geofence->name,
                'fleet_group' => $geofence->fleetGroup?->name,
                'shape_type' => $geofence->shape_type,
                'speed_limit' => $geofence->speed_limit,
                'entrance_alert_enabled' => $geofence->entrance_alert_enabled ? 'yes' : 'no',
                'exit_alert_enabled' => $geofence->exit_alert_enabled ? 'yes' : 'no',
                'trackers_count' => $geofence->tracker_devices_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overspeedRows(?int $tenantId): array
    {
        return AlertEvent::withoutGlobalScope('tenant')
            ->with('trackerDevice:id,display_name,business_label')
            ->where('type', 'overspeed')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest('occurred_at')
            ->limit(1000)
            ->get()
            ->map(fn (AlertEvent $event): array => [
                'tracker' => $event->trackerDevice?->display_name ?? $event->trackerDevice?->business_label,
                'occurred_at' => $this->date($event->occurred_at),
                'resolved_at' => $this->date($event->resolved_at),
                'severity' => data_get($event->metadata, 'severity', $event->resolved_at ? 'resolved' : 'open'),
                'speed' => data_get($event->metadata, 'speed'),
                'speed_limit' => data_get($event->metadata, 'speed_limit'),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventRows(?int $tenantId): array
    {
        return AlertEvent::withoutGlobalScope('tenant')
            ->with('trackerDevice:id,display_name,business_label')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest('occurred_at')
            ->limit(1000)
            ->get()
            ->map(fn (AlertEvent $event): array => [
                'type' => $event->type,
                'tracker' => $event->trackerDevice?->display_name ?? $event->trackerDevice?->business_label,
                'occurred_at' => $this->date($event->occurred_at),
                'resolved_at' => $this->date($event->resolved_at),
                'severity' => data_get($event->metadata, 'severity', $event->resolved_at ? 'resolved' : 'open'),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deviceStatusRows(?int $tenantId): array
    {
        return TrackerDevice::withoutGlobalScope('tenant')
            ->with('lastEvent:id,tracker_device_id,latitude,longitude,speed')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest('last_seen_at')
            ->limit(1000)
            ->get()
            ->map(fn (TrackerDevice $tracker): array => [
                'tracker' => $tracker->display_name,
                'status' => $tracker->status,
                'last_seen_at' => $this->date($tracker->last_seen_at),
                'latitude' => $tracker->lastEvent?->latitude === null ? null : (float) $tracker->lastEvent->latitude,
                'longitude' => $tracker->lastEvent?->longitude === null ? null : (float) $tracker->lastEvent->longitude,
                'speed' => $tracker->lastEvent?->speed === null ? null : (float) $tracker->lastEvent->speed,
                'contract' => trim(($tracker->contract_key ?? 'no-contract').' v'.($tracker->contract_version ?? '-')),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routeRows(?int $tenantId): array
    {
        return NormalizedLocationEvent::withoutGlobalScope('tenant')
            ->with('trackerDevice:id,display_name,business_label')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest('event_timestamp')
            ->limit(1000)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (NormalizedLocationEvent $event): array => [
                'tracker' => $event->trackerDevice?->display_name ?? $event->trackerDevice?->business_label,
                'event_timestamp' => $this->date($event->event_timestamp),
                'latitude' => (float) $event->latitude,
                'longitude' => (float) $event->longitude,
                'speed' => $event->speed === null ? null : (float) $event->speed,
                'heading' => $event->heading === null ? null : (float) $event->heading,
                'distance_km' => (float) data_get($event->normalized_metadata, 'distance_km', 0),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rawPayloadRows(?int $tenantId): array
    {
        return RawPayload::withoutGlobalScope('tenant')
            ->with('trackerDevice:id,display_name,business_label')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest('received_at')
            ->limit(1000)
            ->get()
            ->map(fn (RawPayload $payload): array => [
                'received_at' => $this->date($payload->received_at),
                'tracker' => $payload->trackerDevice?->display_name,
                'identity' => $this->payloadIdentity($payload),
                'contract' => trim(($payload->parser_contract_key ?? 'unknown').' v'.($payload->parser_contract_version ?? '-')),
                'status' => $payload->processing_status,
                'reason' => $payload->rejection_reason,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function analysisRows(?int $tenantId): array
    {
        $events = $this->routeRows($tenantId);

        $summary = [
            'events' => count($events),
            'distance_km' => round((float) collect($events)->sum('distance_km'), 2),
            'moving_time_minutes' => collect($events)->where('speed', '>', 1)->count() * 6,
            'idle_time_minutes' => collect($events)->filter(fn (array $event): bool => (float) ($event['speed'] ?? 0) <= 1)->count() * 6,
            'max_speed' => round((float) collect($events)->max('speed'), 1),
            'average_speed' => round((float) collect($events)->avg('speed'), 1),
        ];

        return collect($summary)
            ->map(fn (mixed $value, string $metric): array => ['metric' => $metric, 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function auditRows(?int $tenantId): array
    {
        return AuditLog::withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest()
            ->limit(1000)
            ->get()
            ->map(fn (AuditLog $audit): array => [
                'created_at' => $this->date($audit->created_at),
                'actor_id' => $audit->actor_id,
                'action' => $audit->action,
                'subject_type' => class_basename($audit->subject_type),
                'subject_id' => $audit->subject_id,
            ])
            ->all();
    }

    private function payloadIdentity(RawPayload $payload): string
    {
        return (string) (
            data_get($payload->metadata, 'device_identity')
            ?? data_get($payload->metadata, 'diagnostics.device_identity')
            ?? data_get($payload->metadata, 'identity')
            ?? 'payload-'.$payload->id
        );
    }

    private function date(mixed $date): ?string
    {
        if ($date instanceof Carbon) {
            return $date->toIso8601String();
        }

        return $date ? Carbon::parse($date)->toIso8601String() : null;
    }
}
