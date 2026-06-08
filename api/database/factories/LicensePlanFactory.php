<?php

namespace Database\Factories;

use App\Models\LicensePlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LicensePlan>
 */
class LicensePlanFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'base_features' => ['tracking', 'reports', 'geofences'],
            'included_device_count' => fake()->numberBetween(5, 100),
            'price_amount' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'MVR',
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
