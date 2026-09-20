<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceConnection extends Model
{
    protected $guarded = [];
    protected $hidden = ['iccid_hash', 'claim_code_hash'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
