<?php

namespace App\Models;

use Database\Factories\AlertEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertEvent extends TenantScopedModel
{
    /** @use HasFactory<AlertEventFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'tracker_device_id',
        'geofence_id',
        'normalized_location_event_id',
        'type',
        'occurred_at',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function trackerDevice(): BelongsTo
    {
        return $this->belongsTo(TrackerDevice::class);
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function normalizedLocationEvent(): BelongsTo
    {
        return $this->belongsTo(NormalizedLocationEvent::class);
    }
}
