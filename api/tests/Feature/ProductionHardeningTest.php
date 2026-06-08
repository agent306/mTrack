<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RawPayload;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_payload_retention_prunes_by_tenant_presets_without_touching_audit_logs(): void
    {
        $tenant30 = Tenant::factory()->create(['raw_payload_retention_days' => 30]);
        $tenant90 = Tenant::factory()->create(['raw_payload_retention_days' => 90]);

        $expired30 = RawPayload::factory()->for($tenant30)->create(['received_at' => now()->subDays(31)]);
        $fresh30 = RawPayload::factory()->for($tenant30)->create(['received_at' => now()->subDays(10)]);
        $fresh90 = RawPayload::factory()->for($tenant90)->create(['received_at' => now()->subDays(60)]);
        $expired90 = RawPayload::factory()->for($tenant90)->create(['received_at' => now()->subDays(91)]);
        $unresolved = RawPayload::factory()->create([
            'tenant_id' => null,
            'received_at' => now()->subDays(120),
        ]);

        AuditLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant30->id,
            'action' => 'old.audit',
            'created_at' => now()->subYears(5),
            'updated_at' => now()->subYears(5),
        ]);

        $this->artisan('mtrack:prune-raw-payloads')
            ->assertSuccessful();

        $this->assertDatabaseMissing('raw_payloads', ['id' => $expired30->id]);
        $this->assertDatabaseHas('raw_payloads', ['id' => $fresh30->id]);
        $this->assertDatabaseHas('raw_payloads', ['id' => $fresh90->id]);
        $this->assertDatabaseMissing('raw_payloads', ['id' => $expired90->id]);
        $this->assertDatabaseMissing('raw_payloads', ['id' => $unresolved->id]);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant30->id, 'action' => 'old.audit']);
    }

    public function test_raw_payload_retention_dry_run_does_not_delete(): void
    {
        $tenant = Tenant::factory()->create(['raw_payload_retention_days' => 30]);
        $payload = RawPayload::factory()->for($tenant)->create(['received_at' => now()->subDays(90)]);

        $this->artisan('mtrack:prune-raw-payloads --dry-run')
            ->assertSuccessful();

        $this->assertDatabaseHas('raw_payloads', ['id' => $payload->id]);
    }

    public function test_production_check_passes_when_launch_configuration_is_present(): void
    {
        Config::set('queue.default', 'redis');
        Config::set('reverb.apps.apps.0.key', 'key');
        Config::set('reverb.apps.apps.0.secret', 'secret');
        Config::set('reverb.apps.apps.0.app_id', 'app');
        Config::set('mtrack.operations.backup_path', 'app/backups');
        Config::set('mtrack.operations.backup_storage_paths', ['app/private', 'app/public']);

        $this->artisan('mtrack:production-check')
            ->assertSuccessful();
    }

    public function test_backup_dry_run_prints_database_storage_and_raw_payload_plan(): void
    {
        $this->artisan('mtrack:backup --dry-run')
            ->expectsOutputToContain('raw_payload_storage')
            ->assertSuccessful();
    }

    public function test_ingestion_route_uses_configured_rate_limit(): void
    {
        $route = Route::getRoutes()->getByName('api.ingest.store');

        $this->assertNotNull($route);
        $this->assertSame('api/ingest', $route->uri());
        $this->assertContains(
            'throttle:'.config('mtrack.ingestion.rate_limit_attempts').','.config('mtrack.ingestion.rate_limit_decay_minutes'),
            $route->gatherMiddleware(),
        );
    }

    public function test_ingestion_route_accepts_common_tracker_http_methods(): void
    {
        $route = Route::getRoutes()->getByName('api.ingest.store');

        $this->assertNotNull($route);

        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->assertContains($method, $route->methods());
        }
    }
}
