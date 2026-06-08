<?php

namespace App\Ingestion\Contracts;

class JimiLocationContract extends AbstractJsonGatewayLocationContract
{
    public function key(): string
    {
        return 'jimi';
    }

    protected function identityPaths(): array
    {
        return ['imei', 'deviceImei', 'device.imei', 'terminalId', 'trackerId', 'deviceId'];
    }
}
