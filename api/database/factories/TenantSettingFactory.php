<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSetting>
 */
class TenantSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => fake()->randomElement(['dashboard.preferences', 'raw_payload.retention', 'api.tokens']),
            'value' => [],
        ];
    }
}
