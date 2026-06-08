<?php

namespace App\Console\Commands;

use App\Providers\HorizonServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ProductionCheck extends Command
{
    protected $signature = 'mtrack:production-check
        {--strict : Fail when production-only expectations are not met}
        {--runtime : Attempt lightweight runtime socket checks such as Reverb reachability}';

    protected $description = 'Validate mTrack launch hardening: retention, backups, Horizon, Reverb, Nixpacks, fixtures, and ingestion safeguards.';

    public function handle(): int
    {
        $strict = (bool) $this->option('strict');
        $checks = [
            $this->check('Raw payload retention presets', $this->retentionPresetsValid(), 'Expected 30/90/180/365 and indefinite audit retention.'),
            $this->check('Backup configuration', filled(config('mtrack.operations.backup_path')) && count(config('mtrack.operations.backup_storage_paths', [])) > 0, 'Backup path and storage coverage are configured.'),
            $this->check('Horizon monitoring', class_exists(HorizonServiceProvider::class) && count(config('horizon.environments.production.supervisor-1', [])) > 0, 'Horizon provider and production supervisor are configured.'),
            $this->check('Reverb configuration', $this->reverbConfigured(), 'Reverb app/server env is present.'),
            $this->check('Coolify/Nixpacks api deployment', $this->nixpacksValid(), 'api/nixpacks.toml contains PHP, Node, nginx, Laravel optimize, and public root.'),
            $this->check('Parser fixture coverage', $this->fixturesValid(), 'Valid, invalid coordinate, missing identity, and malformed fixtures exist.'),
            $this->check('Ingestion rate limit', $this->ingestionThrottleValid(), 'api.ingest.store uses the configured throttle middleware.'),
            $this->check('Payload size limit', (int) config('mtrack.ingestion.max_payload_bytes') > 0, 'MTRACK_INGESTION_MAX_PAYLOAD_BYTES is positive.'),
        ];

        if ($strict) {
            $checks[] = $this->check('Production debug disabled', ! config('app.debug'), 'APP_DEBUG must be false for launch.');
            $checks[] = $this->check('PostgreSQL default database', config('database.default') === 'pgsql', 'Launch target uses PostgreSQL.');
            $checks[] = $this->check('Redis queue', config('queue.default') === 'redis', 'Launch target uses Redis queues for Horizon.');
            $checks[] = $this->check('Redis cache', config('cache.default') === 'redis', 'Launch target uses Redis cache.');
        }

        if ((bool) $this->option('runtime')) {
            $checks[] = $this->check('Reverb runtime socket', $this->reverbReachable(), 'Reverb server host/port accepts TCP connections.');
        }

        $this->table(['Check', 'Status', 'Detail'], collect($checks)->map(fn (array $check): array => [
            $check['name'],
            $check['passed'] ? 'pass' : 'fail',
            $check['detail'],
        ])->all());

        return collect($checks)->every(fn (array $check): bool => $check['passed'])
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @return array{name: string, passed: bool, detail: string}
     */
    private function check(string $name, bool $passed, string $detail): array
    {
        return compact('name', 'passed', 'detail');
    }

    private function retentionPresetsValid(): bool
    {
        return config('mtrack.tenancy.raw_payload_retention_presets') === [30, 90, 180, 365];
    }

    private function reverbConfigured(): bool
    {
        return filled(config('reverb.servers.reverb.host'))
            && filled(config('reverb.servers.reverb.port'))
            && filled(config('reverb.apps.apps.0.key'))
            && filled(config('reverb.apps.apps.0.secret'))
            && filled(config('reverb.apps.apps.0.app_id'));
    }

    private function nixpacksValid(): bool
    {
        $path = base_path('nixpacks.toml');
        $contents = is_file($path) ? file_get_contents($path) : '';

        return str_contains($contents, 'php84')
            && str_contains($contents, 'nodejs_22')
            && str_contains($contents, 'nginx')
            && str_contains($contents, 'php artisan optimize')
            && str_contains($contents, 'NIXPACKS_PHP_ROOT_DIR = "/app/public"');
    }

    private function fixturesValid(): bool
    {
        $required = [
            'demo-json-valid.json',
            'demo-json-invalid-coordinates.json',
            'demo-json-missing-identity.json',
            'demo-json-malformed.json',
        ];

        return collect($required)->every(fn (string $fixture): bool => is_file(base_path("tests/Fixtures/Ingestion/{$fixture}")));
    }

    private function ingestionThrottleValid(): bool
    {
        $route = Route::getRoutes()->getByName('api.ingest.store');
        $expected = 'throttle:'.config('mtrack.ingestion.rate_limit_attempts').','.config('mtrack.ingestion.rate_limit_decay_minutes');

        return $route !== null && collect($route->gatherMiddleware())->contains($expected);
    }

    private function reverbReachable(): bool
    {
        $host = config('reverb.servers.reverb.host');
        $port = (int) config('reverb.servers.reverb.port');
        $socket = @fsockopen((string) $host, $port, $errno, $errstr, 2);

        if (! $socket) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
