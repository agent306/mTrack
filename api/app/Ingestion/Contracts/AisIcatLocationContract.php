<?php

namespace App\Ingestion\Contracts;

class AisIcatLocationContract extends AbstractAisLocationContract
{
    public function key(): string
    {
        return 'ais-icat';
    }

    protected function networkName(): string
    {
        return 'ICAT';
    }
}
