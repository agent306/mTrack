<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TrackerDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackerDevice>
 */
class TrackerDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'display_name' => fake()->unique()->bothify('Tracker ##??'),
            'business_label' => fake()->randomElement(['boat', 'buggy', 'vessel', 'vehicle', 'fleet', 'asset']),
            'contract_key' => 'demo-http',
            'contract_version' => 1,
            'status' => fake()->randomElement(['online', 'offline', 'idle', 'moving']),
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 180)),
            'metadata' => [],
        ];
    }
}
