<?php

namespace App\Ingestion\Contracts;

use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Data\ParsedLocation;
use App\Ingestion\Exceptions\IngestionRejected;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

class Jt808LocationContract extends AbstractJsonGatewayLocationContract
{
    public function key(): string
    {
        return 'jt808';
    }

    protected function identityPaths(): array
    {
        return ['terminalPhone', 'terminal_phone', 'sim', 'imei', 'deviceId', 'device.id'];
    }

    public function parse(IngestionPayload $payload): ParsedLocation
    {
        $body = trim($payload->body);

        if (str_starts_with($body, '{')) {
            return parent::parse($payload);
        }

        return $this->parseBinaryLocationReport($body);
    }

    protected function parseBinaryLocationReport(string $body): ParsedLocation
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $body) ?? '');

        if (str_starts_with($hex, '7E') && str_ends_with($hex, '7E')) {
            $hex = substr($hex, 2, -2);
        }

        $hex = str_replace(['7D02', '7D01'], ['7E', '7D'], $hex);
        $bytes = hex2bin($hex);

        if ($bytes === false || strlen($bytes) < 41) {
            throw new IngestionRejected('JT808 payload is not a valid hex frame.', 'body');
        }

        $messageId = $this->uint16($bytes, 0);
        $bodyLength = $this->uint16($bytes, 2) & 0x03FF;
        $terminalPhone = $this->bcd(substr($bytes, 4, 6));
        $payload = substr($bytes, 12, $bodyLength);

        if ($messageId !== 0x0200) {
            throw new IngestionRejected('JT808 message is not a location report.', 'messageId', [
                'message_id' => strtoupper(str_pad(dechex($messageId), 4, '0', STR_PAD_LEFT)),
            ]);
        }

        if (strlen($payload) < 28) {
            throw new IngestionRejected('JT808 location report body is incomplete.', 'body');
        }

        $latitude = $this->uint32($payload, 8) / 1_000_000;
        $longitude = $this->uint32($payload, 12) / 1_000_000;
        $this->assertCoordinates($latitude, $longitude);

        $speedKmh = $this->uint16($payload, 20) / 10;

        return new ParsedLocation(
            deviceIdentity: $terminalPhone,
            eventTimestamp: $this->bcdTimestamp(substr($payload, 22, 6)),
            latitude: $latitude,
            longitude: $longitude,
            speedMetersPerSecond: $speedKmh / 3.6,
            headingDegrees: (float) $this->uint16($payload, 20),
            altitudeMeters: (float) $this->uint16($payload, 16),
            statusMetadata: [
                'alarm_bits' => strtoupper(bin2hex(substr($payload, 0, 4))),
                'status_bits' => strtoupper(bin2hex(substr($payload, 4, 4))),
            ],
            normalizedMetadata: [
                'protocol_family' => 'JT/T 808',
                'message_id' => '0200',
                'payload_format' => 'binary_hex',
            ],
        );
    }

    private function uint16(string $bytes, int $offset): int
    {
        return unpack('n', substr($bytes, $offset, 2))[1];
    }

    private function uint32(string $bytes, int $offset): int
    {
        return unpack('N', substr($bytes, $offset, 4))[1];
    }

    private function bcd(string $bytes): string
    {
        return ltrim(implode('', array_map(
            fn (int $byte): string => sprintf('%02X', $byte),
            array_values(unpack('C*', $bytes))
        )), '0');
    }

    private function bcdTimestamp(string $bytes): Carbon
    {
        $digits = implode('', array_map(
            fn (int $byte): string => sprintf('%02X', $byte),
            array_values(unpack('C*', $bytes))
        ));

        try {
            return Carbon::createFromFormat('ymdHis', $digits, 'UTC') ?: now();
        } catch (InvalidFormatException) {
            throw new IngestionRejected('JT808 location report timestamp is invalid.', 'timestamp');
        }
    }
}
