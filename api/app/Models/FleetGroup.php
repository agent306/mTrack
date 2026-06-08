<?php

namespace App\Models;

use Database\Factories\FleetGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetGroup extends TenantScopedModel
{
    /** @use HasFactory<FleetGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'visibility',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function trackerDevices(): BelongsToMany
    {
        return $this->belongsToMany(TrackerDevice::class)
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class);
    }
}
