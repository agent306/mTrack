<?php

namespace Database\Factories;

use App\Models\LicenseAllocation;
use App\Models\LicensePlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseAllocation>
 */
class LicenseAllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'license_plan_id' => LicensePlan::factory(),
            'active_device_count' => fake()->numberBetween(1, 25),
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
