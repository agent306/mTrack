<?php

namespace App\Ingestion\Contracts\Concerns;

use App\Ingestion\Exceptions\IngestionRejected;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

trait ParsesLocationPayloads
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $paths
     */
    protected function first(array $data, array $paths): mixed
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
    protected function firstString(array $data, array $paths): ?string
    {
        $value = $this->first($data, $paths);

        return is_scalar($value) ? trim((string) $value) : null;
    }

    protected function parseTimestamp(mixed $value, string $field = 'eventTimestamp'): Carbon
    {
        if ($value === null || $value === '') {
            throw new IngestionRejected('Event timestamp is missing.', $field);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw new IngestionRejected('Event timestamp is invalid.', $field, [
                'value' => $value,
            ]);
        }
    }

    protected function parseNumber(mixed $value, string $field): float
    {
        if (! is_numeric($value)) {
            throw new IngestionRejected("{$field} is missing or not numeric.", $field, [
                'value' => $value,
            ]);
        }

        return (float) $value;
    }

    protected function optionalNumber(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function assertCoordinates(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90) {
            throw new IngestionRejected('Latitude must be between -90 and 90.', 'latitude', ['value' => $latitude]);
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new IngestionRejected('Longitude must be between -180 and 180.', 'longitude', ['value' => $longitude]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function speedMetersPerSecond(array $data): ?float
    {
        $metersPerSecond = $this->optionalNumber($this->first($data, ['speedMetersPerSecond', 'speedMps', 'speed_mps']));

        if ($metersPerSecond !== null) {
            return $metersPerSecond;
        }

        $kilometersPerHour = $this->optionalNumber($this->first($data, ['speedKmh', 'speed_kmh', 'speed']));

        if ($kilometersPerHour !== null) {
            return $kilometersPerHour / 3.6;
        }

        $knots = $this->optionalNumber($this->first($data, ['speedKnots', 'speed_knots', 'sog']));

        return $knots === null ? null : $knots * 0.514444;
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonBody(string $body): array
    {
        $data = json_decode($body, true);

        if (! is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new IngestionRejected('Payload body is not valid JSON.', 'body', [
                'json_error' => json_last_error_msg(),
            ]);
        }

        return $data;
    }
}
