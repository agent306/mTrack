<?php

namespace App\Models;

use Database\Factories\GeofenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Geofence extends TenantScopedModel
{
    /** @use HasFactory<GeofenceFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'fleet_group_id',
        'name',
        'shape_type',
        'shape_geometry',
        'entrance_alert_enabled',
        'exit_alert_enabled',
        'speed_limit',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'shape_geometry' => 'array',
            'entrance_alert_enabled' => 'boolean',
            'exit_alert_enabled' => 'boolean',
            'speed_limit' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function fleetGroup(): BelongsTo
    {
        return $this->belongsTo(FleetGroup::class);
    }

    public function trackerDevices(): BelongsToMany
    {
        return $this->belongsToMany(TrackerDevice::class)
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function alertEvents(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }
}
