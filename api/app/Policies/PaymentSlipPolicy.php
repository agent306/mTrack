<?php

namespace App\Policies;

class PaymentSlipPolicy extends TenantScopedDomainPolicy
{
    protected string $module = 'billing';
}
