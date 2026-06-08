<?php

namespace App\Ingestion\Data;

use Illuminate\Support\Carbon;

class ParsedLocation
{
    /**
     * @param  array<string, mixed>  $statusMetadata
     * @param  array<string, mixed>  $normalizedMetadata
     */
    public function __construct(
        public readonly string $deviceIdentity,
        public readonly Carbon $eventTimestamp,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $speedMetersPerSecond = null,
        public readonly ?float $headingDegrees = null,
        public readonly ?float $altitudeMeters = null,
        public readonly ?float $accuracyMeters = null,
        public readonly array $statusMetadata = [],
        public readonly array $normalizedMetadata = [],
    ) {}
}
