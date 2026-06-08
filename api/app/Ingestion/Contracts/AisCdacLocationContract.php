<?php

namespace App\Ingestion\Contracts;

class AisCdacLocationContract extends AbstractAisLocationContract
{
    public function key(): string
    {
        return 'ais-cdac';
    }

    protected function networkName(): string
    {
        return 'CDAC';
    }
}
