<?php

namespace App\Ingestion\Contracts;

use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Data\ParsedLocation;
use App\Ingestion\Exceptions\IngestionRejected;
use Illuminate\Support\Carbon;

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

        if (str_starts_with(ltrim($payload->body), '*')) {
            return $this->parseH02(trim($payload->body));
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

    /**
     * H02 text frames sent natively by VL512 units, e.g.
     * *HQ,9171701065,V1,123350,A,0413.6411,N,07332.6347,E,000.00,000,061026,FFFFFBFF,...#
     * V1/V5/V6 share this layout; V4 command replies insert the original command before the time.
     */
    private function parseH02(string $body): ParsedLocation
    {
        $frame = null;

        if (preg_match_all('/\*[A-Z]{2},[^#*]+#?/', $body, $matches)) {
            foreach ($matches[0] as $candidate) {
                $fields = explode(',', rtrim($candidate, '#'));
                $offset = ($fields[2] ?? null) === 'V4' ? 1 : 0;

                if (count($fields) >= 12 + $offset && preg_match('/^\d{6}$/', $fields[3 + $offset])) {
                    $frame = $offset > 0 ? [$fields[0], $fields[1], $fields[2], ...array_slice($fields, 4)] : $fields;
                    break;
                }
            }
        }

        if ($frame === null) {
            throw new IngestionRejected('H02 frame does not contain a location report.', 'body');
        }

        [, $identity, $command, $time, $validity, $latitude, $latHemisphere, $longitude, $lonHemisphere, $speedKnots, $course, $date] = $frame;
        $identity = trim($identity);

        if ($identity === '') {
            throw new IngestionRejected('Device identity is missing.', 'deviceIdentity');
        }

        $latitude = $this->h02Coordinate($latitude, $latHemisphere, 'S', 'latitude');
        $longitude = $this->h02Coordinate($longitude, $lonHemisphere, 'W', 'longitude');
        $this->assertCoordinates($latitude, $longitude);

        // V6 frames end with the SIM ICCID, padded with a trailing F.
        $iccid = preg_match('/^(\d{18,20})F?$/i', (string) end($frame), $iccidMatch) ? $iccidMatch[1] : null;

        $timestamp = Carbon::createFromFormat('dmyHis', $date.$time, 'UTC');

        if ($timestamp === false || $timestamp->format('dmyHis') !== $date.$time) {
            throw new IngestionRejected('H02 date/time is invalid.', 'eventTimestamp');
        }

        return new ParsedLocation(
            deviceIdentity: $identity,
            eventTimestamp: $timestamp,
            latitude: $latitude,
            longitude: $longitude,
            speedMetersPerSecond: is_numeric($speedKnots) ? ((float) $speedKnots) * 0.514444 : null,
            headingDegrees: $this->optionalNumber($course),
            statusMetadata: array_filter([
                'gps_fix' => $validity === 'A',
                'device_status' => $frame[12] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
            normalizedMetadata: [
                'protocol_family' => $this->key(),
                'payload_format' => 'h02',
                'raw_protocol' => $command,
            ],
            iccid: $iccid,
        );
    }

    private function h02Coordinate(string $value, string $hemisphere, string $negativeHemisphere, string $field): float
    {
        if (! preg_match('/^(\d+)(\d{2}\.\d+)$/', trim($value), $match)) {
            throw new IngestionRejected("{$field} is missing or not numeric.", $field);
        }

        $degrees = (float) $match[1] + ((float) $match[2]) / 60;

        return strtoupper(trim($hemisphere)) === $negativeHemisphere ? -$degrees : $degrees;
    }
}
