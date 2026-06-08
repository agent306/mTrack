<?php

namespace App\Models;

use Database\Factories\RawPayloadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RawPayload extends TenantScopedModel
{
    /** @use HasFactory<RawPayloadFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'tracker_device_id',
        'parser_contract_key',
        'parser_contract_version',
        'received_at',
        'headers',
        'body_content',
        'body_content_type',
        'processing_status',
        'rejection_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'parser_contract_version' => 'integer',
            'received_at' => 'datetime',
            'headers' => 'array',
            'metadata' => 'array',
        ];
    }

    public function trackerDevice(): BelongsTo
    {
        return $this->belongsTo(TrackerDevice::class);
    }

    public function normalizedLocationEvent(): HasOne
    {
        return $this->hasOne(NormalizedLocationEvent::class);
    }
}
