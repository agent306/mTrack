<?php

namespace App\Models;

use Database\Factories\LicenseAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseAllocation extends TenantScopedModel
{
    /** @use HasFactory<LicenseAllocationFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'license_plan_id',
        'active_device_count',
        'starts_at',
        'expires_at',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'active_device_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function licensePlan(): BelongsTo
    {
        return $this->belongsTo(LicensePlan::class);
    }
}
