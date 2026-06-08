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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_query_string_payload_is_stored_and_normalized_for_get_callbacks(): void
    {
        $tenant = Tenant::factory()->create();
        $tracker = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'contract_key' => 'demo-json',
            'contract_version' => 1,
            'metadata' => ['device_identity' => 'query-tracker-001'],
        ]);

        $response = $this->call(
            method: 'GET',
            uri: '/api/ingest?'.http_build_query([
                'deviceId' => 'query-tracker-001',
                'timestamp' => '2026-06-08T04:30:00Z',
                'latitude' => '24.8607',
                'longitude' => '67.0011',
                'speedMps' => '20',
            ]),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'normalized')
            ->assertJsonPath('tracker_device_id', $tracker->id);

        $rawPayload = RawPayload::firstOrFail();
        $event = NormalizedLocationEvent::firstOrFail();

        $this->assertSame('GET', $rawPayload->headers['x-ingest-method']);
        $this->assertStringContainsString('query-tracker-001', $rawPayload->headers['x-ingest-query']);
        $this->assertSame('application/json', $rawPayload->body_content_type);
        $this->assertSame('20.00', $event->speed);
    }

    #[DataProvider('trackerProtocolPayloads')]
    public function test_registered_tracker_protocol_strategies_normalize_payloads(
        string $contractKey,
        string $fixture,
        string $deviceIdentity,
        string $expectedProtocolFamily,
        string $expectedPayloadFormat,
        string $contentType = 'application/json',
    ): void {
        $tenant = Tenant::factory()->create();
        $tracker = TrackerDevice::factory()->create([
            'tenant_id' => $tenant->id,
            'contract_key' => $contractKey,
            'contract_version' => 1,
            'metadata' => ['device_identity' => $deviceIdentity],
        ]);

        $response = $this->postRawIngestionFor($this->fixture($fixture), $contentType);

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'normalized')
            ->assertJsonPath('tracker_device_id', $tracker->id);

        $event = NormalizedLocationEvent::firstOrFail();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame($contractKey, $event->parser_contract_key);
        $this->assertSame($deviceIdentity, $event->normalized_metadata['device_identity']);
        $this->assertSame($expectedProtocolFamily, $event->normalized_metadata['protocol_family']);
        $this->assertSame($expectedPayloadFormat, $event->normalized_metadata['payload_format']);
        if ($contractKey === 'jt808') {
            $this->assertSame('20.00', $event->speed);
        }
        $this->assertNotNull($tracker->refresh()->last_event_id);
    }

    /**
     * @return array<string, array{string, string, string, string, string, 5?: string}>
     */
    public static function trackerProtocolPayloads(): array
    {
        return [
            'JIMI gateway JSON' => ['jimi', 'jimi-valid.json', 'JIMI-867000111222333', 'jimi', 'json'],
            'JT/T 808 binary location report' => ['jt808', 'jt808-location.hex', '13912345678', 'JT/T 808', 'binary_hex', 'application/octet-stream'],
            'JT/T 1078 over 808 JSON gateway' => ['jt1078-808', 'jt1078-location.json', '1078-013912345678', 'jt1078-808', 'json'],
            'AIS ICAT JSON gateway' => ['ais-icat', 'ais-icat-valid.json', '412345678', 'ais-icat', 'json'],
            'AIS NIC JSON gateway' => ['ais-nic', 'ais-nic-valid.json', '413000111', 'ais-nic', 'json'],
            'AIS CDAC JSON gateway' => ['ais-cdac', 'ais-cdac-valid.json', '414000222', 'ais-cdac', 'json'],
            'VL512 CSV gateway' => ['vl512-gnss', 'vl512-csv-valid.txt', 'VL512-001', 'vl512-gnss', 'csv', 'text/plain'],
        ];
    }

    public function test_auto_detected_unregistered_tracker_payload_keeps_contract_for_assignment(): void
    {
        $this->postRawIngestion($this->fixture('jimi-valid.json'))
            ->assertUnprocessable()
            ->assertJsonPath('diagnostics.failed_field', 'deviceIdentity');

        $rawPayload = RawPayload::firstOrFail();

        $this->assertSame('jimi', $rawPayload->parser_contract_key);
        $this->assertSame(1, $rawPayload->parser_contract_version);
        $this->assertSame('JIMI-867000111222333', $rawPayload->metadata['diagnostics']['device_identity']);
        $this->assertDatabaseCount('normalized_location_events', 0);
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
        return $this->postRawIngestionFor($body);
    }

    private function postRawIngestionFor(string $body, string $contentType = 'application/json')
    {
        return $this->call(
            method: 'POST',
            uri: '/api/ingest',
            server: ['CONTENT_TYPE' => $contentType],
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
