<?php

namespace Database\Factories;

use App\Models\LicensePlan;
use App\Models\LicenseRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseRequest>
 */
class LicenseRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'license_plan_id' => LicensePlan::factory(),
            'requested_by_user_id' => null,
            'reviewed_by_user_id' => null,
            'request_type' => fake()->randomElement(['add', 'renew']),
            'requested_device_count' => fake()->numberBetween(1, 20),
            'amount' => fake()->randomFloat(2, 25, 2000),
            'currency' => 'USD',
            'status' => 'pending',
            'rejection_reason' => null,
            'metadata' => [],
        ];
    }

    public function requestedBy(User $user): static
    {
        return $this->state(fn () => [
            'tenant_id' => $user->tenant_id,
            'requested_by_user_id' => $user->id,
        ]);
    }
}
