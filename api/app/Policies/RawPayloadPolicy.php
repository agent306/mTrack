<?php

namespace App\Policies;

class RawPayloadPolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'settings';
}
