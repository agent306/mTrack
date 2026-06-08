<?php

namespace App\Policies;

class LicenseRequestPolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'billing';
}
