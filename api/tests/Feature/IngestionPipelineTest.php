<?php

namespace Tests\Feature;

use App\Models\NormalizedLocationEvent;
use App\Models\RawPayload;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TrackerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class IngestionPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_json_payload_is_stored_and_normalized(): void
    {
        $tenant = Tenant::factory()->create();
        $tracker = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'contract_key' => 'demo-json',
            'contract_version' => 1,
            'metadata' => ['device_identity' => 'fixture-tracker-001'],
        ]);

        $response = $this->postRawIngestion($this->fixture('demo-json-valid.json'));

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'normalized')
            ->assertJsonPath('tracker_device_id', $tracker->id);

        $rawPayload = RawPayload::firstOrFail();
        $event = NormalizedLocationEvent::firstOrFail();

        $this->assertSame('normalized', $rawPayload->processing_status);
        $this->assertSame($tenant->id, $rawPayload->tenant_id);
        $this->assertSame($tracker->id, $rawPayload->tracker_device_id);
        $this->assertSame($rawPayload->id, $event->raw_payload_id);
        $this->assertSame('20.00', $event->speed);
        $this->assertSame($event->id, $tracker->refresh()->last_event_id);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'raw_payload.normalized',
            'subject_type' => RawPayload::class,
            'subject_id' => $rawPayload->id,
        ]);
    }

    public function test_missing_identity_payload_is_rejected_with_diagnostics_after_raw_capture(): void
    {
        $this->postRawIngestion($this->fixture('demo-json-missing-identity.json'))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('diagnostics.failed_field', 'deviceIdentity');

        $rawPayload = RawPayload::firstOrFail();

        $this->assertSame('rejected', $rawPayload->processing_status);
        $this->assertNull($rawPayload->tenant_id);
        $this->assertSame('Device identity is missing.', $rawPayload->rejection_reason);
        $this->assertDatabaseCount('normalized_location_events', 0);
    }

    public function test_invalid_coordinates_are_rejected_without_normalized_event(): void
    {
        TrackerDevice::factory()->create([
            'contract_key' => 'demo-json',
            'contract_version' => 1,
            'metadata' => ['device_identity' => 'fixture-tracker-001'],
        ]);

        $this->postRawIngestion($this->fixture('demo-json-invalid-coordinates.json'))
            ->assertUnprocessable()
            ->assertJsonPath('diagnostics.failed_field', 'latitude');

        $this->assertDatabaseHas('raw_payloads', [
            'processing_status' => 'rejected',
            'rejection_reason' => 'Latitude must be between -90 and 90.',
        ]);
        $this->assertDatabaseCount('normalized_location_events', 0);
    }

    public function test_malformed_body_is_still_stored_as_rejected_raw_payload(): void
    {
        $body = $this->fixture('demo-json-malformed.json');

        $this->postRawIngestion($body)
            ->assertUnprocessable()
            ->assertJsonPath('diagnostics.failed_field', 'body');

        $rawPayload = RawPayload::firstOrFail();

        $this->assertSame($body, $rawPayload->body_content);
        $this->assertSame('rejected', $rawPayload->processing_status);
    }

    public function test_payload_size_limit_creates_rejection_record(): void
    {
        Config::set('mtrack.ingestion.max_payload_bytes', 10);

        $this->postRawIngestion($this->fixture('demo-json-valid.json'))
            ->assertStatus(413)
            ->assertJsonPath('diagnostics.failed_field', 'body');

        $this->assertDatabaseHas('raw_payloads', [
            'processing_status' => 'rejected',
        ]);
        $this->assertDatabaseCount('normalized_location_events', 0);
    }

    public function test_failed_payload_can_be_replayed_without_silent_duplicates(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = $this->operator($tenant);
        $tracker = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'contract_key' => 'demo-json',
            'contract_version' => 1,
            'metadata' => ['device_identity' => 'fixture-tracker-001'],
        ]);

        $rawPayload = RawPayload::factory()->create([
            'tenant_id' => $tenant->id,
            'parser_contract_key' => 'demo-json',
            'parser_contract_version' => 1,
            'received_at' => now(),
            'headers' => ['content-type' => 'application/json'],
            'body_content' => $this->fixture('demo-json-valid.json'),
            'body_content_type' => 'application/json',
            'processing_status' => 'rejected',
            'rejection_reason' => 'Previous parser failure.',
        ]);

        $this->actingAs($operator, 'sanctum')
            ->postJson("/api/raw-payloads/{$rawPayload->id}/replay")
            ->assertCreated()
            ->assertJsonPath('tracker_device_id', $tracker->id);

        $this->assertDatabaseCount('normalized_location_events', 1);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id' => $operator->id,
            'action' => 'raw_payload.replayed',
        ]);

        $this->actingAs($operator, 'sanctum')
            ->postJson("/api/raw-payloads/{$rawPayload->id}/replay")
            ->assertConflict();

        $this->assertDatabaseCount('normalized_location_events', 1);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/Ingestion/{$name}"));
    }

    private function postRawIngestion(string $body)
    {
        return $this->call(
            method: 'POST',
            uri: '/api/ingest/demo-json',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
    }

    private function operator(Tenant $tenant): User
    {
        $role = Role::factory()->create([
            'tenant_id' => $tenant->id,
            'permissions' => [
                'modules' => [
                    'settings' => 'edit',
                ],
            ],
        ]);

        $user = User::factory()->for($tenant)->create();
        $user->roles()->attach($role);

        return $user->load('roles', 'tenant');
    }
}
