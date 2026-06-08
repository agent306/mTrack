<?php

namespace App\Ingestion\Exceptions;

use RuntimeException;

class IngestionRejected extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        string $message,
        public readonly ?string $failedField = null,
        public readonly array $diagnostics = [],
    ) {
        parent::__construct($message);
    }
}
