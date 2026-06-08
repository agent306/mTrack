<?php

namespace App\Billing;

use App\Models\LicenseAllocation;
use App\Models\LicensePlan;
use App\Models\LicenseRequest;
use App\Models\PaymentSlip;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingManager
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function createLicenseRequest(User $user, LicensePlan $plan, string $requestType, int $deviceCount, ?Request $request = null): LicenseRequest
    {
        $licenseRequest = LicenseRequest::query()->create([
            'tenant_id' => $user->tenant_id,
            'license_plan_id' => $plan->id,
            'requested_by_user_id' => $user->id,
            'request_type' => $requestType,
            'requested_device_count' => $deviceCount,
            'amount' => (float) $plan->price_amount * $deviceCount,
            'currency' => $plan->currency,
            'status' => 'pending',
            'metadata' => [
                'plan' => $plan->name,
                'included_device_count' => $plan->included_device_count,
                'base_features' => $plan->base_features ?? [],
            ],
        ]);

        $this->audit->record($user, 'license_request.created', $licenseRequest, $user->tenant_id, [
            'request_type' => $requestType,
            'requested_device_count' => $deviceCount,
            'license_plan_id' => $plan->id,
        ], $request);

        return $licenseRequest;
    }

    public function uploadPaymentSlip(User $user, LicenseRequest $licenseRequest, string $originalFilename, ?float $amount = null, ?Request $request = null): PaymentSlip
    {
        $slip = PaymentSlip::query()->create([
            'tenant_id' => $user->tenant_id,
            'license_request_id' => $licenseRequest->id,
            'uploaded_by_user_id' => $user->id,
            'file_path' => 'manual-slips/'.Str::uuid().'-'.$originalFilename,
            'original_filename' => $originalFilename,
            'amount' => $amount ?? (float) $licenseRequest->amount,
            'status' => 'pending',
            'metadata' => ['uploaded_from' => 'customer_web'],
        ]);

        $this->audit->record($user, 'payment_slip.uploaded', $slip, $user->tenant_id, [
            'license_request_id' => $licenseRequest->id,
            'amount' => $slip->amount,
        ], $request);

        return $slip;
    }

    public function approvePaymentSlip(User $admin, PaymentSlip $paymentSlip, ?Request $request = null): LicenseAllocation
    {
        return DB::transaction(function () use ($admin, $paymentSlip, $request): LicenseAllocation {
            $paymentSlip->loadMissing('licenseRequest.licensePlan');
            $licenseRequest = $paymentSlip->licenseRequest;

            $paymentSlip->forceFill([
                'status' => 'approved',
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $licenseRequest?->forceFill([
                'status' => 'approved',
                'reviewed_by_user_id' => $admin->id,
                'rejection_reason' => null,
            ])->save();

            $allocation = $this->activateAllocation($admin, $paymentSlip, $licenseRequest);

            $this->audit->record($admin, 'payment_slip.approved', $paymentSlip, $paymentSlip->tenant_id, [
                'amount' => $paymentSlip->amount,
                'license_request_id' => $paymentSlip->license_request_id,
            ], $request);

            if ($licenseRequest) {
                $this->audit->record($admin, 'license_request.approved', $licenseRequest, $licenseRequest->tenant_id, [
                    'payment_slip_id' => $paymentSlip->id,
                    'license_allocation_id' => $allocation->id,
                ], $request);
            }

            $this->audit->record($admin, $licenseRequest?->request_type === 'renew' ? 'license_allocation.renewed' : 'license_allocation.activated', $allocation, $allocation->tenant_id, [
                'license_request_id' => $licenseRequest?->id,
                'payment_slip_id' => $paymentSlip->id,
                'active_device_count' => $allocation->active_device_count,
                'expires_at' => $allocation->expires_at?->toIso8601String(),
            ], $request);

            return $allocation;
        });
    }

    public function rejectPaymentSlip(User $admin, PaymentSlip $paymentSlip, string $reason, ?Request $request = null): void
    {
        DB::transaction(function () use ($admin, $paymentSlip, $reason, $request): void {
            $paymentSlip->loadMissing('licenseRequest');

            $paymentSlip->forceFill([
                'status' => 'rejected',
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $paymentSlip->licenseRequest?->forceFill([
                'status' => 'rejected',
                'reviewed_by_user_id' => $admin->id,
                'rejection_reason' => $reason,
            ])->save();

            $this->audit->record($admin, 'payment_slip.rejected', $paymentSlip, $paymentSlip->tenant_id, [
                'license_request_id' => $paymentSlip->license_request_id,
                'rejection_reason' => $reason,
            ], $request);

            if ($paymentSlip->licenseRequest) {
                $this->audit->record($admin, 'license_request.rejected', $paymentSlip->licenseRequest, $paymentSlip->tenant_id, [
                    'payment_slip_id' => $paymentSlip->id,
                    'rejection_reason' => $reason,
                ], $request);
            }
        });
    }

    private function activateAllocation(User $admin, PaymentSlip $paymentSlip, ?LicenseRequest $licenseRequest): LicenseAllocation
    {
        abort_unless($licenseRequest !== null, 422, 'Payment slip is not linked to a license request.');

        $now = now();
        $metadata = [
            'license_request_id' => $licenseRequest->id,
            'payment_slip_id' => $paymentSlip->id,
            'request_type' => $licenseRequest->request_type,
            'approved_by_user_id' => $admin->id,
            'approved_at' => $now->toIso8601String(),
        ];

        if ($licenseRequest->request_type === 'renew') {
            $allocation = LicenseAllocation::withoutGlobalScope('tenant')
                ->where('tenant_id', $licenseRequest->tenant_id)
                ->where('license_plan_id', $licenseRequest->license_plan_id)
                ->where('status', 'active')
                ->orderByDesc('expires_at')
                ->first();

            if ($allocation) {
                $expiresFrom = $allocation->expires_at && $allocation->expires_at->greaterThan($now)
                    ? $allocation->expires_at
                    : $now;

                $allocation->forceFill([
                    'active_device_count' => max($allocation->active_device_count, $licenseRequest->requested_device_count),
                    'expires_at' => $expiresFrom->copy()->addYear(),
                    'metadata' => [
                        ...($allocation->metadata ?? []),
                        'last_renewal' => $metadata,
                    ],
                ])->save();

                $this->markTenantActive($licenseRequest);

                return $allocation->refresh();
            }
        }

        $allocation = LicenseAllocation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $licenseRequest->tenant_id,
            'license_plan_id' => $licenseRequest->license_plan_id,
            'active_device_count' => $licenseRequest->requested_device_count,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addYear(),
            'status' => 'active',
            'metadata' => $metadata,
        ]);

        $this->markTenantActive($licenseRequest);

        return $allocation;
    }

    private function markTenantActive(LicenseRequest $licenseRequest): void
    {
        $licenseRequest->tenant?->forceFill([
            'billing_status' => 'active',
        ])->save();
    }
}
