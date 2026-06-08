<?php

namespace App\Policies;

class TenantSettingPolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'settings';
}
