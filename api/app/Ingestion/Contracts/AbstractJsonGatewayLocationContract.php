<?php

namespace App\Ingestion\Contracts;

use App\Ingestion\Contracts\Concerns\ParsesLocationPayloads;
use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Data\ParsedLocation;
use App\Ingestion\Exceptions\IngestionRejected;

abstract class AbstractJsonGatewayLocationContract implements ParserContract
{
    use ParsesLocationPayloads;

    /**
     * @return array<int, string>
     */
    abstract protected function identityPaths(): array;

    /**
     * @return array<int, string>
     */
    protected function timestampPaths(): array
    {
        return ['eventTimestamp', 'timestamp', 'time', 'gpsTime', 'gps_time', 'location.time'];
    }

    /**
     * @return array<int, string>
     */
    protected function latitudePaths(): array
    {
        return ['latitude', 'lat', 'gps.lat', 'location.lat', 'position.lat'];
    }

    /**
     * @return array<int, string>
     */
    protected function longitudePaths(): array
    {
        return ['longitude', 'lng', 'lon', 'gps.lng', 'gps.lon', 'location.lng', 'location.lon', 'position.lng', 'position.lon'];
    }

    public function version(): int
    {
        return 1;
    }

    public function parse(IngestionPayload $payload): ParsedLocation
    {
        $data = $this->jsonBody($payload->body);
        $deviceIdentity = $this->firstString($data, $this->identityPaths())
            ?? $payload->headers['x-device-id']
            ?? $payload->headers['x-tracker-id']
            ?? null;

        if (! is_string($deviceIdentity) || trim($deviceIdentity) === '') {
            throw new IngestionRejected('Device identity is missing.', 'deviceIdentity');
        }

        $latitude = $this->parseNumber($this->first($data, $this->latitudePaths()), 'latitude');
        $longitude = $this->parseNumber($this->first($data, $this->longitudePaths()), 'longitude');
        $this->assertCoordinates($latitude, $longitude);

        return new ParsedLocation(
            deviceIdentity: trim($deviceIdentity),
            eventTimestamp: $this->parseTimestamp($this->first($data, $this->timestampPaths())),
            latitude: $latitude,
            longitude: $longitude,
            speedMetersPerSecond: $this->speedMetersPerSecond($data),
            headingDegrees: $this->optionalNumber($this->first($data, ['headingDegrees', 'heading', 'course', 'gps.heading'])),
            altitudeMeters: $this->optionalNumber($this->first($data, ['altitudeMeters', 'altitude', 'gps.altitude'])),
            accuracyMeters: $this->optionalNumber($this->first($data, ['accuracyMeters', 'accuracy', 'gps.accuracy', 'hdop'])),
            statusMetadata: array_filter([
                'device_status' => $this->firstString($data, ['deviceStatus', 'status', 'state']),
                'battery_percent' => $this->optionalNumber($this->first($data, ['batteryPercent', 'battery_percent', 'battery'])),
                'ignition' => $this->first($data, ['ignition', 'acc']),
            ], fn ($value) => $value !== null),
            normalizedMetadata: array_filter([
                'protocol_family' => $this->key(),
                'source_sequence' => $this->first($data, ['sourceSequence', 'sequence', 'seq', 'serial']),
                'firmware' => $this->firstString($data, ['firmware', 'device.firmware']),
                'raw_protocol' => $this->firstString($data, ['protocol', 'messageType']),
                'payload_format' => 'json',
            ], fn ($value) => $value !== null),
        );
    }
}
