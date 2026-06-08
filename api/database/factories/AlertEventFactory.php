<?php

namespace Database\Factories;

use App\Models\AlertEvent;
use App\Models\NormalizedLocationEvent;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertEvent>
 */
class AlertEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tracker_device_id' => TrackerDevice::factory(),
            'geofence_id' => null,
            'normalized_location_event_id' => null,
            'type' => fake()->randomElement([
                'geofence_entrance',
                'geofence_exit',
                'overspeed',
                'online',
                'offline',
                'stale',
            ]),
            'occurred_at' => now(),
            'resolved_at' => null,
            'metadata' => [],
        ];
    }

    public function forLocationEvent(NormalizedLocationEvent $event): static
    {
        return $this->state(fn () => [
            'tenant_id' => $event->tenant_id,
            'tracker_device_id' => $event->tracker_device_id,
            'normalized_location_event_id' => $event->id,
        ]);
    }
}
