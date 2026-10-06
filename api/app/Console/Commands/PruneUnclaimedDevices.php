<?php

namespace App\Console\Commands;

use App\Models\DeviceConnection;
use App\Models\RawPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneUnclaimedDevices extends Command
{
    protected $signature = 'mtrack:prune-unclaimed-devices
        {--hours=24 : Dispose of devices still unclaimed this long after first contact}
        {--dry-run : Count devices without deleting them}';

    protected $description = 'Delete devices nobody claimed within the claim window, with their held packets and raw payloads.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $devices = DeviceConnection::query()
            ->whereNull('tracker_device_id')
            ->where('first_seen_at', '<', now()->subHours($hours))
            ->get(['id', 'imei']);

        $payloads = 0;

        if (! $this->option('dry-run')) {
            foreach ($devices as $device) {
                DB::transaction(function () use ($device, &$payloads) {
                    $payloads += RawPayload::withoutGlobalScope('tenant')
                        ->whereNull('tracker_device_id')
                        ->where('metadata->pending_device_identity', $device->imei)
                        ->delete();
                    // device_packets cascade with the connection.
                    DeviceConnection::whereKey($device->id)->whereNull('tracker_device_id')->delete();
                });
            }
        }

        $this->line(json_encode([
            'dry_run' => (bool) $this->option('dry-run'),
            'hours' => $hours,
            'devices' => $devices->count(),
            'raw_payloads' => $payloads,
        ], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
