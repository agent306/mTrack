<?php

namespace App\Models;

use Database\Factories\TrackerDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackerDevice extends TenantScopedModel
{
    /** @use HasFactory<TrackerDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'display_name',
        'business_label',
        'contract_key',
        'contract_version',
        'status',
        'last_event_id',
        'last_seen_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'contract_version' => 'integer',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function fleetGroups(): BelongsToMany
    {
        return $this->belongsToMany(FleetGroup::class)
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function geofences(): BelongsToMany
    {
        return $this->belongsToMany(Geofence::class)
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function rawPayloads(): HasMany
    {
        return $this->hasMany(RawPayload::class);
    }

    public function locationEvents(): HasMany
    {
        return $this->hasMany(NormalizedLocationEvent::class);
    }

    public function lastEvent(): BelongsTo
    {
        return $this->belongsTo(NormalizedLocationEvent::class, 'last_event_id');
    }

    public function alertEvents(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }
}
