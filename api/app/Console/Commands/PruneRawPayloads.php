<?php

namespace App\Console\Commands;

use App\Models\RawPayload;
use App\Models\Tenant;
use Illuminate\Console\Command;

class PruneRawPayloads extends Command
{
    protected $signature = 'mtrack:prune-raw-payloads
        {--tenant-id= : Limit cleanup to one tenant id}
        {--dry-run : Count rows without deleting them}';

    protected $description = 'Delete raw payloads according to each tenant retention preset while leaving audit logs indefinitely.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant-id');
        $summary = [
            'dry_run' => $dryRun,
            'tenants' => [],
            'unresolved' => 0,
            'deleted' => 0,
        ];

        $tenants = Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->whereKey((int) $tenantId))
            ->orderBy('id')
            ->get();

        if ($tenantId !== null && $tenants->isEmpty()) {
            $this->error("Tenant {$tenantId} was not found.");

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $days = $this->validatedPreset((int) $tenant->raw_payload_retention_days);
            $count = $this->pruneTenant($tenant->id, $days, $dryRun);

            $summary['tenants'][] = [
                'tenant_id' => $tenant->id,
                'retention_days' => $days,
                'deleted' => $count,
            ];
            $summary['deleted'] += $count;
        }

        if ($tenantId === null) {
            $defaultDays = (int) config('mtrack.operations.unresolved_raw_payload_retention_days', 90);
            $summary['unresolved'] = $this->pruneTenant(null, $defaultDays, $dryRun);
            $summary['deleted'] += $summary['unresolved'];
        }

        $this->line(json_encode($summary, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function pruneTenant(?int $tenantId, int $days, bool $dryRun): int
    {
        $query = RawPayload::withoutGlobalScope('tenant')
            ->where('received_at', '<', now()->subDays($days))
            ->when($tenantId === null, fn ($query) => $query->whereNull('tenant_id'), fn ($query) => $query->where('tenant_id', $tenantId));

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    private function validatedPreset(int $days): int
    {
        $presets = config('mtrack.tenancy.raw_payload_retention_presets', [30, 90, 180, 365]);

        return in_array($days, $presets, true) ? $days : 90;
    }
}
