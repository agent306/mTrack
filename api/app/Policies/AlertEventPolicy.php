<?php

namespace App\Policies;

class AlertEventPolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'events';
}
