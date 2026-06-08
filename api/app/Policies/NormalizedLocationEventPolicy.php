<?php

namespace App\Policies;

class NormalizedLocationEventPolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'live';
}
