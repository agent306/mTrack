<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class MTrackBackup extends Command
{
    protected $signature = 'mtrack:backup
        {--dry-run : Validate backup inputs and print the planned manifest}
        {--path= : Override the configured backup directory}';

    protected $description = 'Create a PostgreSQL/sqlite database backup plus local uploaded-file copies for launch operations.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $root = $this->backupRoot();
        $stamp = now()->format('Ymd-His');
        $backupDir = $root.DIRECTORY_SEPARATOR.$stamp;
        $manifest = [
            'created_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'database' => $this->databasePlan($backupDir),
            'storage' => $this->storagePlan($backupDir),
            'raw_payload_storage' => 'database.raw_payloads',
        ];

        if (! $dryRun) {
            File::ensureDirectoryExists($backupDir);
            $this->backupDatabase($manifest['database']);
            $this->backupStorage($manifest['storage']);
            File::put($backupDir.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        }

        $this->line(json_encode($manifest, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function backupRoot(): string
    {
        $path = $this->option('path') ?: config('mtrack.operations.backup_path');

        return Str::startsWith($path, DIRECTORY_SEPARATOR) ? $path : storage_path((string) $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function databasePlan(string $backupDir): array
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        return [
            'connection' => $connection,
            'database' => $database,
            'target' => $backupDir.DIRECTORY_SEPARATOR.'database.sql',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function storagePlan(string $backupDir): array
    {
        return collect(config('mtrack.operations.backup_storage_paths', []))
            ->map(fn (string $path): array => [
                'source' => storage_path($path),
                'target' => $backupDir.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], '-', $path),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function backupDatabase(array $plan): void
    {
        if ($plan['connection'] === 'sqlite') {
            File::copy((string) $plan['database'], (string) $plan['target']);

            return;
        }

        if ($plan['connection'] !== 'pgsql') {
            $this->warn("Database backup for {$plan['connection']} is not implemented. Manifest only.");

            return;
        }

        $process = new Process([
            'pg_dump',
            '--no-owner',
            '--no-acl',
            '--file='.$plan['target'],
            (string) $plan['database'],
        ]);
        $process->setEnv(array_filter([
            'PGHOST' => config('database.connections.pgsql.host'),
            'PGPORT' => (string) config('database.connections.pgsql.port'),
            'PGUSER' => config('database.connections.pgsql.username'),
            'PGPASSWORD' => config('database.connections.pgsql.password'),
        ], fn ($value) => $value !== null && $value !== ''));
        $process->mustRun();
    }

    /**
     * @param  array<int, array<string, string>>  $plans
     */
    private function backupStorage(array $plans): void
    {
        foreach ($plans as $plan) {
            if (! File::exists($plan['source'])) {
                File::ensureDirectoryExists($plan['target']);

                continue;
            }

            File::copyDirectory($plan['source'], $plan['target']);
        }
    }
}
