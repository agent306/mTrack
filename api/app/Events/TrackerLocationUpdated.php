<?php

namespace App\Events;

use App\Models\NormalizedLocationEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackerLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly NormalizedLocationEvent $locationEvent) {}

    public function broadcastAs(): string
    {
        return 'tracker.location.updated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenants.{$this->locationEvent->tenant_id}.trackers"),
            new PrivateChannel("tenants.{$this->locationEvent->tenant_id}.trackers.{$this->locationEvent->tracker_device_id}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $tracker = $this->locationEvent->trackerDevice;

        return [
            'event' => 'tracker.location.updated',
            'tenant_id' => $this->locationEvent->tenant_id,
            'tracker_device_id' => $this->locationEvent->tracker_device_id,
            'normalized_location_event_id' => $this->locationEvent->id,
            'display_name' => $tracker?->display_name,
            'status' => $tracker?->status,
            'event_timestamp' => $this->locationEvent->event_timestamp?->toISOString(),
            'received_timestamp' => $this->locationEvent->received_timestamp?->toISOString(),
            'latitude' => (float) $this->locationEvent->latitude,
            'longitude' => (float) $this->locationEvent->longitude,
            'speed_meters_per_second' => $this->decimalToFloat($this->locationEvent->speed),
            'heading_degrees' => $this->decimalToFloat($this->locationEvent->heading),
            'altitude_meters' => $this->decimalToFloat($this->locationEvent->altitude),
            'accuracy_meters' => $this->decimalToFloat($this->locationEvent->accuracy),
            'status_metadata' => $this->locationEvent->status_metadata ?? [],
            'normalized_metadata' => $this->locationEvent->normalized_metadata ?? [],
        ];
    }

    private function decimalToFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
