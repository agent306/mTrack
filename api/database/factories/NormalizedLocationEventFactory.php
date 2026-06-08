<?php

namespace Database\Factories;

use App\Models\NormalizedLocationEvent;
use App\Models\RawPayload;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NormalizedLocationEvent>
 */
class NormalizedLocationEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tracker_device_id' => TrackerDevice::factory(),
            'raw_payload_id' => null,
            'parser_contract_key' => 'demo-http',
            'parser_contract_version' => 1,
            'event_timestamp' => now(),
            'received_timestamp' => now(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'speed' => fake()->randomFloat(2, 0, 120),
            'heading' => fake()->randomFloat(2, 0, 359),
            'altitude' => fake()->optional()->randomFloat(2, 0, 900),
            'accuracy' => fake()->optional()->randomFloat(2, 1, 50),
            'status_metadata' => ['motion' => 'moving'],
            'normalized_metadata' => [],
        ];
    }

    public function forTracker(TrackerDevice $trackerDevice, ?RawPayload $rawPayload = null): static
    {
        return $this->state(fn () => [
            'tenant_id' => $trackerDevice->tenant_id,
            'tracker_device_id' => $trackerDevice->id,
            'raw_payload_id' => $rawPayload?->id,
            'parser_contract_key' => $trackerDevice->contract_key ?? 'demo-http',
            'parser_contract_version' => $trackerDevice->contract_version ?? 1,
        ]);
    }
}
