<?php

namespace App\Models;

use Database\Factories\LicenseRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseRequest extends TenantScopedModel
{
    /** @use HasFactory<LicenseRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'license_plan_id',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'request_type',
        'requested_device_count',
        'amount',
        'currency',
        'status',
        'rejection_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requested_device_count' => 'integer',
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function licensePlan(): BelongsTo
    {
        return $this->belongsTo(LicensePlan::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function paymentSlips(): HasMany
    {
        return $this->hasMany(PaymentSlip::class);
    }
}
