<?php

namespace App\Tracking;

use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\IngestionProcessor;
use App\Models\{DeviceConnection, TrackerDevice};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeviceGateway
{
    public function receive(array $packet): void
    {
        DB::transaction(function () use ($packet) {
            DeviceConnection::query()->insertOrIgnore([
                'imei' => $packet['imei'], 'first_seen_at' => now(), 'last_seen_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $device = DeviceConnection::where('imei', $packet['imei'])->lockForUpdate()->firstOrFail();
            $device->last_seen_at = now();
            if (! empty($packet['iccid'])) {
                $device->iccid_hash = hash('sha256', $packet['iccid']);
            }
            // Link trackers assigned before self-service onboarding was introduced.
            if (! $device->tracker_device_id) {
                $existing = TrackerDevice::withoutGlobalScopes()->where('metadata->device_identity', $device->imei)->first();
                if ($existing) {
                    $device->tracker_device_id = $existing->id;
                }
            }
            $device->save();
            $fingerprint = hash('sha256', $device->imei.'|'.$packet['packet_hex']);
            DB::table('device_packets')->insertOrIgnore([
                'device_connection_id' => $device->id, 'fingerprint' => $fingerprint,
                'packet_hex' => $packet['packet_hex'], 'protocol' => $packet['protocol'],
                'location' => isset($packet['location']) ? json_encode($packet['location'], JSON_THROW_ON_ERROR) : null,
                'received_at' => now(),
            ]);
            $this->processPending($device);
        }, 3);
    }

    public function processPending(DeviceConnection $device): void
    {
        if (! $device->tracker_device_id) {
            return;
        }
        $tracker = TrackerDevice::withoutGlobalScopes()->findOrFail($device->tracker_device_id);
        $packets = DB::table('device_packets')->where('device_connection_id', $device->id)
            ->whereNull('processed_at')->orderBy('id')->limit(500)->get();
        foreach ($packets as $packet) {
            if ($packet->location) {
                $location = json_decode($packet->location, true, flags: JSON_THROW_ON_ERROR);
                $outcome = app(IngestionProcessor::class)->processIncoming(new IngestionPayload(
                    body: json_encode(['imei' => $device->imei, ...$location], JSON_THROW_ON_ERROR),
                    contentType: 'application/json', headers: ['x-device-id' => $device->imei],
                    receivedAt: Carbon::parse($packet->received_at),
                ), $tracker->contract_key ?? 'vl512-gnss');
                if (! $outcome->accepted()) {
                    throw new \RuntimeException('Tracker location could not be persisted.');
                }
            }
            DB::table('device_packets')->where('id', $packet->id)->update(['processed_at' => now()]);
        }
    }
}
