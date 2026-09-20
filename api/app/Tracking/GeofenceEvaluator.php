<?php

namespace App\Tracking;

use App\Models\{AlertEvent, Geofence, NormalizedLocationEvent, TrackerDevice};
use Illuminate\Support\Facades\DB;

class GeofenceEvaluator
{
    public function evaluate(TrackerDevice $tracker, NormalizedLocationEvent $event): void
    {
        $fences = Geofence::withoutGlobalScopes()->where('tenant_id', $tracker->tenant_id)->get();
        foreach ($fences as $fence) {
            $assigned = $fence->trackerDevices()->withoutGlobalScopes()->pluck('tracker_devices.id');
            if ($assigned->isNotEmpty() && ! $assigned->contains($tracker->id)) {
                continue;
            }
            if ($fence->fleet_group_id && ! $tracker->fleetGroups()->whereKey($fence->fleet_group_id)->exists()) {
                continue;
            }
            $key = ['geofence_id' => $fence->id, 'tracker_device_id' => $tracker->id];
            $state = DB::table('geofence_states')->where($key)->lockForUpdate()->first();
            if ($state && $event->event_timestamp->lessThanOrEqualTo($state->observed_at)) {
                continue;
            }
            $inside = $this->contains($fence->shape_type, $fence->shape_geometry, (float) $event->latitude, (float) $event->longitude);
            // First observation establishes a baseline; it is not an invented entry.
            if ($state && (bool) $state->inside !== $inside) {
                $type = $inside ? 'geofence_entry' : 'geofence_exit';
                if ($inside ? $fence->entrance_alert_enabled : $fence->exit_alert_enabled) {
                    AlertEvent::withoutGlobalScopes()->create([
                        'tenant_id' => $tracker->tenant_id, 'tracker_device_id' => $tracker->id,
                        'geofence_id' => $fence->id, 'normalized_location_event_id' => $event->id,
                        'type' => $type, 'occurred_at' => $event->event_timestamp,
                        'metadata' => ['latitude' => (float) $event->latitude, 'longitude' => (float) $event->longitude],
                    ]);
                }
            }
            DB::table('geofence_states')->updateOrInsert($key, ['inside' => $inside, 'observed_at' => $event->event_timestamp]);
        }
    }

    public function contains(string $type, array $geometry, float $lat, float $lng): bool
    {
        if ($type === 'circle') {
            $center = $geometry['center'] ?? [0, 0];
            $center = [$center['lat'] ?? $center[0] ?? 0, $center['lng'] ?? $center[1] ?? 0];
            $a = sin(deg2rad($lat - $center[0]) / 2) ** 2
                + cos(deg2rad($lat)) * cos(deg2rad($center[0])) * sin(deg2rad($lng - $center[1]) / 2) ** 2;
            return 6371000 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a))) <= ($geometry['radius'] ?? $geometry['radius_m'] ?? 0);
        }
        $points = $geometry['points'] ?? [];
        $points = array_map(fn ($p) => [$p['lat'] ?? $p[0], $p['lng'] ?? $p[1]], $points);
        $inside = false;
        for ($i = 0, $j = count($points) - 1; $i < count($points); $j = $i++) {
            [$yi, $xi] = $points[$i];
            [$yj, $xj] = $points[$j];
            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }
        return $inside;
    }
}
