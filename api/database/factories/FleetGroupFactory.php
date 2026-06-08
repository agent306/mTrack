<?php

namespace Database\Factories;

use App\Models\FleetGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetGroup>
 */
class FleetGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'visibility' => fake()->randomElement(['public', 'private']),
            'metadata' => [],
        ];
    }
}
