<?php

namespace App\Console\Commands;

use App\Models\DeviceConnection;
use Illuminate\Console\Command;

class IssueDeviceClaimCode extends Command
{
    protected $signature = 'mtrack:claim-code {imei}';
    protected $description = 'Issue a private claim code after independently verifying device ownership';

    public function handle(): int
    {
        $device = DeviceConnection::where('imei', $this->argument('imei'))->whereNull('tracker_device_id')->first();
        if (! $device) {
            $this->error('An unclaimed connected device is required.');
            return self::FAILURE;
        }
        $code = strtoupper(bin2hex(random_bytes(12)));
        $device->update(['claim_code_hash' => hash('sha256', $code)]);
        $this->line($code);
        return self::SUCCESS;
    }
}
