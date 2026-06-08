<?php

namespace App\Policies;

class FleetGroupPolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'my_fleets';
}
