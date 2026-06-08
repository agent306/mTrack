<?php

namespace App\Models;

use Database\Factories\TenantSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TenantSetting extends TenantScopedModel
{
    /** @use HasFactory<TenantSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
