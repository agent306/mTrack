<?php

namespace App\Ingestion\Contracts;

use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Data\ParsedLocation;
use App\Ingestion\Exceptions\IngestionRejected;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class DemoJsonLocationContract implements ParserContract
{
    public function key(): string
    {
        return 'demo-json';
    }

    public function version(): int
    {
        return 1;
    }

    public function parse(IngestionPayload $payload): ParsedLocation
    {
        $data = json_decode($payload->body, true);

        if (! is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new IngestionRejected('Payload body is not valid JSON.', 'body', [
                'json_error' => json_last_error_msg(),
            ]);
        }

        $deviceIdentity = $this->firstString($data, ['trackerId', 'deviceId', 'device.id'])
            ?? $payload->headers['x-device-id']
            ?? $payload->headers['x-tracker-id']
            ?? null;

        if (! is_string($deviceIdentity) || trim($deviceIdentity) === '') {
            throw new IngestionRejected('Device identity is missing.', 'deviceIdentity');
        }

        $eventTimestamp = $this->parseTimestamp($this->first($data, ['eventTimestamp', 'timestamp', 'gps.timestamp']));
        $latitude = $this->parseNumber($this->first($data, ['latitude', 'lat', 'gps.lat']), 'latitude');
        $longitude = $this->parseNumber($this->first($data, ['longitude', 'lng', 'gps.lng']), 'longitude');

        if ($latitude < -90 || $latitude > 90) {
            throw new IngestionRejected('Latitude must be between -90 and 90.', 'latitude', ['value' => $latitude]);
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new IngestionRejected('Longitude must be between -180 and 180.', 'longitude', ['value' => $longitude]);
        }

        return new ParsedLocation(
            deviceIdentity: trim($deviceIdentity),
            eventTimestamp: $eventTimestamp,
            latitude: $latitude,
            longitude: $longitude,
            speedMetersPerSecond: $this->speedMetersPerSecond($data),
            headingDegrees: $this->optionalNumber($this->first($data, ['headingDegrees', 'heading', 'gps.heading'])),
            altitudeMeters: $this->optionalNumber($this->first($data, ['altitudeMeters', 'altitude', 'gps.altitude'])),
            accuracyMeters: $this->optionalNumber($this->first($data, ['accuracyMeters', 'accuracy', 'gps.accuracy'])),
            statusMetadata: array_filter([
                'device_status' => $this->firstString($data, ['deviceStatus', 'status']),
                'battery_percent' => $this->optionalNumber($this->first($data, ['batteryPercent', 'battery'])),
            ], fn ($value) => $value !== null),
            normalizedMetadata: array_filter([
                'source_sequence' => $this->first($data, ['sourceSequence', 'sequence', 'seq']),
                'firmware' => $this->firstString($data, ['firmware', 'device.firmware']),
            ], fn ($value) => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $paths
     */
    private function first(array $data, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($data, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $paths
     */
    private function firstString(array $data, array $paths): ?string
    {
        $value = $this->first($data, $paths);

        return is_scalar($value) ? (string) $value : null;
    }

    private function parseTimestamp(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            throw new IngestionRejected('Event timestamp is missing.', 'eventTimestamp');
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            throw new IngestionRejected('Event timestamp is invalid.', 'eventTimestamp', [
                'value' => $value,
            ]);
        }
    }

    private function parseNumber(mixed $value, string $field): float
    {
        if (! is_numeric($value)) {
            throw new IngestionRejected("{$field} is missing or not numeric.", $field, [
                'value' => $value,
            ]);
        }

        return (float) $value;
    }

    private function optionalNumber(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function speedMetersPerSecond(array $data): ?float
    {
        $metersPerSecond = $this->optionalNumber($this->first($data, ['speedMetersPerSecond', 'speedMps']));

        if ($metersPerSecond !== null) {
            return $metersPerSecond;
        }

        $kilometersPerHour = $this->optionalNumber($this->first($data, ['speedKmh', 'speed']));

        return $kilometersPerHour === null ? null : $kilometersPerHour / 3.6;
    }
}
