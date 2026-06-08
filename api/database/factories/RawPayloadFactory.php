<?php

namespace Database\Factories;

use App\Models\RawPayload;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawPayload>
 */
class RawPayloadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tracker_device_id' => null,
            'parser_contract_key' => 'demo-http',
            'parser_contract_version' => 1,
            'received_at' => now(),
            'headers' => ['content-type' => 'application/json'],
            'body_content' => '{"lat":24.8607,"lng":67.0011}',
            'body_content_type' => 'application/json',
            'processing_status' => 'received',
            'rejection_reason' => null,
            'metadata' => [],
        ];
    }

    public function forTracker(TrackerDevice $trackerDevice): static
    {
        return $this->state(fn () => [
            'tenant_id' => $trackerDevice->tenant_id,
            'tracker_device_id' => $trackerDevice->id,
            'parser_contract_key' => $trackerDevice->contract_key,
            'parser_contract_version' => $trackerDevice->contract_version,
        ]);
    }
}
