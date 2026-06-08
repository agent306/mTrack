<?php

namespace App\Models;

use Database\Factories\PaymentSlipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSlip extends TenantScopedModel
{
    /** @use HasFactory<PaymentSlipFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'license_request_id',
        'uploaded_by_user_id',
        'reviewed_by_user_id',
        'file_path',
        'original_filename',
        'amount',
        'status',
        'rejection_reason',
        'reviewed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function licenseRequest(): BelongsTo
    {
        return $this->belongsTo(LicenseRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
