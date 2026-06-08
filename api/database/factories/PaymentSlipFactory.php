<?php

namespace Database\Factories;

use App\Models\LicenseRequest;
use App\Models\PaymentSlip;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSlip>
 */
class PaymentSlipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'license_request_id' => LicenseRequest::factory(),
            'uploaded_by_user_id' => null,
            'reviewed_by_user_id' => null,
            'file_path' => 'payment-slips/demo-slip.pdf',
            'original_filename' => 'demo-slip.pdf',
            'amount' => fake()->randomFloat(2, 25, 2000),
            'status' => 'pending',
            'rejection_reason' => null,
            'reviewed_at' => null,
            'metadata' => [],
        ];
    }

    public function forRequest(LicenseRequest $licenseRequest, ?User $uploadedBy = null): static
    {
        return $this->state(fn () => [
            'tenant_id' => $licenseRequest->tenant_id,
            'license_request_id' => $licenseRequest->id,
            'uploaded_by_user_id' => $uploadedBy?->id,
            'amount' => $licenseRequest->amount,
        ]);
    }
}
