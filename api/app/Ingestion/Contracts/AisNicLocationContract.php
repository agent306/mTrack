<?php

namespace App\Ingestion\Contracts;

class AisNicLocationContract extends AbstractAisLocationContract
{
    public function key(): string
    {
        return 'ais-nic';
    }

    protected function networkName(): string
    {
        return 'NIC';
    }
}
