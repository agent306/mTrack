<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'status' => 'active',
            'billing_status' => 'trial',
            'raw_payload_retention_days' => 90,
            'metadata' => [],
        ];
    }
}
