<?php

namespace App\Ingestion\Contracts;

use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Data\ParsedLocation;
use App\Ingestion\Exceptions\IngestionRejected;

abstract class AbstractAisLocationContract extends AbstractJsonGatewayLocationContract
{
    abstract protected function networkName(): string;

    protected function identityPaths(): array
    {
        return ['mmsi', 'ais.mmsi', 'vessel.mmsi', 'deviceId'];
    }

    protected function latitudePaths(): array
    {
        return ['latitude', 'lat', 'ais.lat', 'position.lat'];
    }

    protected function longitudePaths(): array
    {
        return ['longitude', 'lon', 'lng', 'ais.lon', 'position.lon'];
    }

    public function parse(IngestionPayload $payload): ParsedLocation
    {
        $body = trim($payload->body);

        if (str_starts_with($body, '{')) {
            $parsed = parent::parse($payload);

            return new ParsedLocation(
                deviceIdentity: $parsed->deviceIdentity,
                eventTimestamp: $parsed->eventTimestamp,
                latitude: $parsed->latitude,
                longitude: $parsed->longitude,
                speedMetersPerSecond: $parsed->speedMetersPerSecond,
                headingDegrees: $parsed->headingDegrees,
                altitudeMeters: $parsed->altitudeMeters,
                accuracyMeters: $parsed->accuracyMeters,
                statusMetadata: $parsed->statusMetadata,
                normalizedMetadata: [
                    ...$parsed->normalizedMetadata,
                    'ais_network' => $this->networkName(),
                ],
            );
        }

        return $this->parseNmeaPositionReport($body);
    }

    private function parseNmeaPositionReport(string $body): ParsedLocation
    {
        $line = collect(preg_split('/\R/', $body) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => str_starts_with($line, '!AIVDM') || str_starts_with($line, '!AIVDO'));

        if (! is_string($line)) {
            throw new IngestionRejected('AIS payload must contain an AIVDM/AIVDO sentence or JSON body.', 'body');
        }

        $withoutChecksum = explode('*', $line, 2)[0];
        $fields = explode(',', $withoutChecksum);
        $payload = $fields[5] ?? null;

        if (! is_string($payload) || $payload === '') {
            throw new IngestionRejected('AIS sentence is missing encoded payload.', 'body');
        }

        $bits = $this->sixBitPayload($payload);
        $messageType = $this->unsignedBits($bits, 0, 6);

        if (! in_array($messageType, [1, 2, 3, 18, 19], true)) {
            throw new IngestionRejected('AIS message is not a supported position report.', 'messageType', [
                'message_type' => $messageType,
            ]);
        }

        $mmsi = (string) $this->unsignedBits($bits, 8, 30);
        $speedKnots = $messageType >= 18
            ? $this->unsignedBits($bits, 46, 10) / 10
            : $this->unsignedBits($bits, 50, 10) / 10;
        $longitude = $messageType >= 18
            ? $this->signedBits($bits, 57, 28) / 600000
            : $this->signedBits($bits, 61, 28) / 600000;
        $latitude = $messageType >= 18
            ? $this->signedBits($bits, 85, 27) / 600000
            : $this->signedBits($bits, 89, 27) / 600000;
        $heading = $messageType >= 18
            ? $this->unsignedBits($bits, 124, 9)
            : $this->unsignedBits($bits, 128, 9);

        $this->assertCoordinates($latitude, $longitude);

        return new ParsedLocation(
            deviceIdentity: $mmsi,
            eventTimestamp: now(),
            latitude: $latitude,
            longitude: $longitude,
            speedMetersPerSecond: $speedKnots >= 102.3 ? null : $speedKnots * 0.514444,
            headingDegrees: $heading >= 360 ? null : (float) $heading,
            statusMetadata: [
                'ais_message_type' => $messageType,
            ],
            normalizedMetadata: [
                'protocol_family' => 'AIS',
                'ais_network' => $this->networkName(),
                'payload_format' => 'nmea',
            ],
        );
    }

    private function sixBitPayload(string $payload): string
    {
        $bits = '';

        foreach (str_split($payload) as $character) {
            $value = ord($character) - 48;

            if ($value > 40) {
                $value -= 8;
            }

            $bits .= str_pad(decbin($value), 6, '0', STR_PAD_LEFT);
        }

        return $bits;
    }

    private function unsignedBits(string $bits, int $offset, int $length): int
    {
        return bindec(substr($bits, $offset, $length));
    }

    private function signedBits(string $bits, int $offset, int $length): int
    {
        $segment = substr($bits, $offset, $length);
        $value = bindec($segment);

        if ($segment[0] === '1') {
            $value -= 1 << $length;
        }

        return $value;
    }
}
