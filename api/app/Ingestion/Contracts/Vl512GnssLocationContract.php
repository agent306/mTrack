<?php

namespace App\Ingestion\Contracts;

use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Data\ParsedLocation;
use App\Ingestion\Exceptions\IngestionRejected;

class Vl512GnssLocationContract extends AbstractJsonGatewayLocationContract
{
    public function key(): string
    {
        return 'vl512-gnss';
    }

    protected function identityPaths(): array
    {
        return ['imei', 'deviceImei', 'device.imei', 'trackerId', 'deviceId'];
    }

    public function parse(IngestionPayload $payload): ParsedLocation
    {
        if (str_starts_with(trim($payload->body), '{')) {
            return parent::parse($payload);
        }

        $parts = str_getcsv(trim($payload->body));

        if (count($parts) < 5) {
            throw new IngestionRejected('VL512 payload must be JSON or CSV with identity, timestamp, latitude, longitude, and speed.', 'body');
        }

        [$identity, $timestamp, $latitude, $longitude, $speedKmh] = $parts;
        $identity = trim((string) $identity);

        if ($identity === '') {
            throw new IngestionRejected('Device identity is missing.', 'deviceIdentity');
        }

        $latitude = $this->parseNumber($latitude, 'latitude');
        $longitude = $this->parseNumber($longitude, 'longitude');
        $this->assertCoordinates($latitude, $longitude);

        return new ParsedLocation(
            deviceIdentity: $identity,
            eventTimestamp: $this->parseTimestamp($timestamp),
            latitude: $latitude,
            longitude: $longitude,
            speedMetersPerSecond: is_numeric($speedKmh) ? ((float) $speedKmh) / 3.6 : null,
            headingDegrees: $this->optionalNumber($parts[5] ?? null),
            statusMetadata: array_filter([
                'device_status' => $parts[6] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
            normalizedMetadata: [
                'protocol_family' => $this->key(),
                'payload_format' => 'csv',
            ],
        );
    }
}
