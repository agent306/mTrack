<?php

namespace App\Ingestion\Data;

use Illuminate\Support\Carbon;

class IngestionPayload
{
    /**
     * @param  array<string, string|null>  $headers
     */
    public function __construct(
        public readonly string $body,
        public readonly ?string $contentType,
        public readonly array $headers,
        public readonly Carbon $receivedAt,
    ) {}
}
