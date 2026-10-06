<?php

namespace App\Ingestion\Exceptions;

use App\Ingestion\Contracts\ParserContract;
use App\Ingestion\Data\ParsedLocation;

/**
 * A valid location from a device that no tracker owns yet. It can be held for claiming
 * when the identity is claimable; otherwise it is rejected like any unresolved identity.
 */
class UnclaimedTracker extends IngestionRejected
{
    /**
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        string $message,
        public readonly ParserContract $contract,
        public readonly ParsedLocation $parsed,
        array $diagnostics = [],
    ) {
        parent::__construct($message, 'deviceIdentity', $diagnostics);
    }
}
