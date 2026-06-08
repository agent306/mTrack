<?php

namespace App\Models;

use Database\Factories\LicensePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicensePlan extends Model
{
    /** @use HasFactory<LicensePlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_features',
        'included_device_count',
        'price_amount',
        'currency',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'base_features' => 'array',
            'included_device_count' => 'integer',
            'price_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LicenseAllocation::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LicenseRequest::class);
    }
}
