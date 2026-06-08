<?php

namespace App\Policies;

class GeofencePolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'geofence';
}
