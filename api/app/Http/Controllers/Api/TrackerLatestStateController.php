<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrackerDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackerLatestStateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $trackers = TrackerDevice::query()
            ->with('lastEvent')
            ->orderBy('display_name')
            ->get()
            ->map(fn (TrackerDevice $trackerDevice): array => $this->serialize($trackerDevice))
            ->values();

        return response()->json(['data' => $trackers]);
    }

    public function show(Request $request, TrackerDevice $trackerDevice): JsonResponse
    {
        return response()->json([
            'data' => $this->serialize($trackerDevice->loadMissing('lastEvent')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(TrackerDevice $trackerDevice): array
    {
        $event = $trackerDevice->lastEvent;

        return [
            'tenant_id' => $trackerDevice->tenant_id,
            'tracker_device_id' => $trackerDevice->id,
            'display_name' => $trackerDevice->display_name,
            'business_label' => $trackerDevice->business_label,
            'status' => $trackerDevice->status,
            'last_seen_at' => $trackerDevice->last_seen_at?->toISOString(),
            'last_event' => $event ? [
                'normalized_location_event_id' => $event->id,
                'event_timestamp' => $event->event_timestamp?->toISOString(),
                'received_timestamp' => $event->received_timestamp?->toISOString(),
                'latitude' => (float) $event->latitude,
                'longitude' => (float) $event->longitude,
                'speed_meters_per_second' => $this->decimalToFloat($event->speed),
                'heading_degrees' => $this->decimalToFloat($event->heading),
                'altitude_meters' => $this->decimalToFloat($event->altitude),
                'accuracy_meters' => $this->decimalToFloat($event->accuracy),
                'status_metadata' => $event->status_metadata ?? [],
                'normalized_metadata' => $event->normalized_metadata ?? [],
            ] : null,
        ];
    }

    private function decimalToFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
