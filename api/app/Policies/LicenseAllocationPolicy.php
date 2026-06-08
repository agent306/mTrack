<?php

namespace App\Policies;

class LicenseAllocationPolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'billing';
}
