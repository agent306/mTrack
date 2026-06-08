<?php

namespace App\Policies;

class TrackerDevicePolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'my_fleets';
}
