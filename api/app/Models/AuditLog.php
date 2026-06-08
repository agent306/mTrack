<?php

namespace App\Models;

class AuditLog extends TenantScopedModel
{
    protected $fillable = [
        'tenant_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
