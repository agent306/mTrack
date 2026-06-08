<?php

namespace App\Ingestion\Contracts;

use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Data\ParsedLocation;

interface ParserContract
{
    public function key(): string;

    public function version(): int;

    public function parse(IngestionPayload $payload): ParsedLocation;
}
