<?php

namespace Database\Factories;

use App\Models\FleetGroup;
use App\Models\Geofence;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Geofence>
 */
class GeofenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fleet_group_id' => null,
            'name' => fake()->unique()->words(2, true),
            'shape_type' => 'circle',
            'shape_geometry' => [
                'center' => ['lat' => 24.8607, 'lng' => 67.0011],
                'radius_meters' => 500,
            ],
            'entrance_alert_enabled' => true,
            'exit_alert_enabled' => true,
            'speed_limit' => fake()->optional()->randomFloat(2, 40, 100),
            'metadata' => [],
        ];
    }

    public function forGroup(FleetGroup $fleetGroup): static
    {
        return $this->state(fn () => [
            'tenant_id' => $fleetGroup->tenant_id,
            'fleet_group_id' => $fleetGroup->id,
        ]);
    }
}
