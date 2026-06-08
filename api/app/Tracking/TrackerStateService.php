<?php

namespace App\Tracking;

use App\Models\AlertEvent;
use App\Models\NormalizedLocationEvent;
use App\Models\TrackerDevice;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TrackerStateService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function markLive(TrackerDevice $trackerDevice, NormalizedLocationEvent $locationEvent): void
    {
        $previousStatus = $trackerDevice->status;
        $status = $this->liveStatusFor($locationEvent);

        $trackerDevice->forceFill([
            'status' => $status,
            'last_event_id' => $locationEvent->id,
            'last_seen_at' => $locationEvent->event_timestamp,
        ])->save();

        if (in_array($previousStatus, ['offline', 'stale', 'no_data'], true)) {
            $this->createAlert($trackerDevice, 'online', $locationEvent->event_timestamp, $locationEvent);
        }
    }

    /**
     * @return array<string, int>
     */
    public function markInactiveTrackers(?int $tenantId = null, ?Carbon $now = null): array
    {
        $now ??= now();
        $summary = [
            'no_data' => 0,
            'stale' => 0,
            'offline' => 0,
        ];

        TrackerDevice::withoutGlobalScopes()
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($trackers) use ($now, &$summary): void {
                foreach ($trackers as $tracker) {
                    $status = $this->inactiveStatusFor($tracker, $now);

                    if ($status === null || $tracker->status === $status) {
                        continue;
                    }

                    $tracker->forceFill(['status' => $status])->save();
                    $this->createAlert($tracker, $status, $now);
                    $summary[$status]++;
                }
            });

        return $summary;
    }

    private function liveStatusFor(NormalizedLocationEvent $locationEvent): string
    {
        $status = $locationEvent->status_metadata['device_status'] ?? null;

        if (is_string($status) && $status !== '') {
            return $status;
        }

        return ((float) ($locationEvent->speed ?? 0)) > 0 ? 'moving' : 'online';
    }

    private function inactiveStatusFor(TrackerDevice $trackerDevice, Carbon $now): ?string
    {
        if ($trackerDevice->last_seen_at === null) {
            return 'no_data';
        }

        if ($trackerDevice->last_seen_at->lte($now->copy()->subMinutes((int) config('mtrack.realtime.offline_after_minutes')))) {
            return 'offline';
        }

        if ($trackerDevice->last_seen_at->lte($now->copy()->subMinutes((int) config('mtrack.realtime.stale_after_minutes')))) {
            return 'stale';
        }

        return null;
    }

    private function createAlert(TrackerDevice $trackerDevice, string $type, Carbon $occurredAt, ?NormalizedLocationEvent $locationEvent = null): void
    {
        $alert = AlertEvent::query()->create([
            'tenant_id' => $trackerDevice->tenant_id,
            'tracker_device_id' => $trackerDevice->id,
            'normalized_location_event_id' => $locationEvent?->id,
            'type' => $type,
            'occurred_at' => $occurredAt,
            'metadata' => [
                'tracker_status' => $type,
            ],
        ]);

        $this->audit->record(null, 'alert_event.created', $alert, $trackerDevice->tenant_id, [
            'type' => $type,
            'tracker_device_id' => $trackerDevice->id,
            'normalized_location_event_id' => $locationEvent?->id,
        ]);
    }
}
