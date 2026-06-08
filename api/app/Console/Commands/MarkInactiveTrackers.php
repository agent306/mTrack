<?php

namespace App\Console\Commands;

use App\Tracking\TrackerStateService;
use Illuminate\Console\Command;

class MarkInactiveTrackers extends Command
{
    protected $signature = 'trackers:mark-inactive {--tenant-id=}';

    protected $description = 'Mark trackers as no-data, stale, or offline based on last seen timestamps.';

    public function handle(TrackerStateService $trackerStates): int
    {
        $tenantId = $this->option('tenant-id');
        $summary = $trackerStates->markInactiveTrackers($tenantId === null ? null : (int) $tenantId);

        $this->components->info(sprintf(
            'Tracker inactivity scan complete: %d no-data, %d stale, %d offline.',
            $summary['no_data'],
            $summary['stale'],
            $summary['offline'],
        ));

        return self::SUCCESS;
    }
}
