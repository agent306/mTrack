<?php

namespace App\Models;

use Database\Factories\NormalizedLocationEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NormalizedLocationEvent extends TenantScopedModel
{
    /** @use HasFactory<NormalizedLocationEventFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'tracker_device_id',
        'raw_payload_id',
        'parser_contract_key',
        'parser_contract_version',
        'event_timestamp',
        'received_timestamp',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'altitude',
        'accuracy',
        'status_metadata',
        'normalized_metadata',
    ];

    protected function casts(): array
    {
        return [
            'parser_contract_version' => 'integer',
            'event_timestamp' => 'datetime',
            'received_timestamp' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed' => 'decimal:2',
            'heading' => 'decimal:2',
            'altitude' => 'decimal:2',
            'accuracy' => 'decimal:2',
            'status_metadata' => 'array',
            'normalized_metadata' => 'array',
        ];
    }

    public function trackerDevice(): BelongsTo
    {
        return $this->belongsTo(TrackerDevice::class);
    }

    public function rawPayload(): BelongsTo
    {
        return $this->belongsTo(RawPayload::class);
    }

    public function alertEvents(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }
}
