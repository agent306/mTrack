<?php

namespace App\Ingestion\Data;

use App\Models\NormalizedLocationEvent;
use App\Models\RawPayload;

class IngestionOutcome
{
    public function __construct(
        public readonly RawPayload $rawPayload,
        public readonly ?NormalizedLocationEvent $event = null,
    ) {}

    public function accepted(): bool
    {
        return $this->event !== null && $this->rawPayload->processing_status === 'normalized';
    }
}
