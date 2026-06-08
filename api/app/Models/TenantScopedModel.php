<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

abstract class TenantScopedModel extends Model
{
    use BelongsToTenant;
}
