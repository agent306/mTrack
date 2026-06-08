<?php

namespace App\Support;

use App\Models\Tenant;

class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }
}
