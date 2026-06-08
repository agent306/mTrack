<?php

namespace App\Ingestion;

use App\Events\TrackerLocationUpdated;
use App\Ingestion\Data\IngestionOutcome;
use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Exceptions\IngestionRejected;
use App\Models\AuditLog;
use App\Models\NormalizedLocationEvent;
use App\Models\RawPayload;
use App\Models\TrackerDevice;
use App\Tracking\TrackerStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class IngestionProcessor
{
    public function __construct(
        private readonly ContractRegistry $contracts,
        private readonly TrackerStateService $trackerStates,
    ) {}

    public function processIncoming(string $contractKey, IngestionPayload $payload): IngestionOutcome
    {
        $contract = $this->contracts->resolve($contractKey);

        $rawPayload = RawPayload::query()->create([
            'parser_contract_key' => $contract->key(),
            'parser_contract_version' => $contract->version(),
            'received_at' => $payload->receivedAt,
            'headers' => $payload->headers,
            'body_content' => $payload->body,
            'body_content_type' => $payload->contentType,
            'processing_status' => 'received',
            'metadata' => [],
        ]);

        $this->logRawPayload('received', $rawPayload, [
            'headers' => $payload->headers,
            'body' => $payload->body,
            'content_type' => $payload->contentType,
            'received_at' => $payload->receivedAt->toISOString(),
        ]);

        $maxPayloadBytes = (int) config('mtrack.ingestion.max_payload_bytes');

        if (strlen($payload->body) > $maxPayloadBytes) {
            return $this->reject($rawPayload, new IngestionRejected(
                "Payload exceeds {$maxPayloadBytes} byte limit.",
                'body',
                ['max_payload_bytes' => $maxPayloadBytes, 'actual_payload_bytes' => strlen($payload->body)],
            ));
        }

        return $this->normalize($rawPayload);
    }

    public function replay(RawPayload $rawPayload, ?string $contractKey = null, ?int $actorId = null): IngestionOutcome
    {
        if ($rawPayload->normalizedLocationEvent()->exists()) {
            throw new InvalidArgumentException('Raw payload already has a normalized event and cannot be replayed without a deduplication strategy.');
        }

        if ($contractKey !== null) {
            $contract = $this->contracts->resolve($contractKey);

            $rawPayload->forceFill([
                'parser_contract_key' => $contract->key(),
                'parser_contract_version' => $contract->version(),
            ])->save();
        }

        $rawPayload->forceFill([
            'processing_status' => 'received',
            'rejection_reason' => null,
            'metadata' => [
                ...($rawPayload->metadata ?? []),
                'replay' => [
                    'requested_at' => now()->toISOString(),
                    'contract_key' => $rawPayload->parser_contract_key,
                    'contract_version' => $rawPayload->parser_contract_version,
                ],
            ],
        ])->save();

        $this->logRawPayload('replay_requested', $rawPayload, [
            'actor_id' => $actorId,
            'body' => $rawPayload->body_content,
        ]);

        $this->audit('raw_payload.replayed', $rawPayload, actorId: $actorId);

        return $this->normalize($rawPayload, $actorId);
    }

    private function normalize(RawPayload $rawPayload, ?int $actorId = null): IngestionOutcome
    {
        try {
            if (! is_string($rawPayload->parser_contract_key)) {
                throw new IngestionRejected('Parser contract key is missing.', 'parser_contract_key');
            }

            $contract = $this->contracts->resolve($rawPayload->parser_contract_key);
            $parsed = $contract->parse(new IngestionPayload(
                body: $rawPayload->body_content,
                contentType: $rawPayload->body_content_type,
                headers: $rawPayload->headers ?? [],
                receivedAt: $rawPayload->received_at,
            ));

            $tracker = $this->resolveTracker($parsed->deviceIdentity, $contract->key(), $contract->version());

            $outcome = DB::transaction(function () use ($rawPayload, $tracker, $contract, $parsed, $actorId): IngestionOutcome {
                $event = NormalizedLocationEvent::query()->create([
                    'tenant_id' => $tracker->tenant_id,
                    'tracker_device_id' => $tracker->id,
                    'raw_payload_id' => $rawPayload->id,
                    'parser_contract_key' => $contract->key(),
                    'parser_contract_version' => $contract->version(),
                    'event_timestamp' => $parsed->eventTimestamp,
                    'received_timestamp' => $rawPayload->received_at,
                    'latitude' => $parsed->latitude,
                    'longitude' => $parsed->longitude,
                    'speed' => $parsed->speedMetersPerSecond,
                    'heading' => $parsed->headingDegrees,
                    'altitude' => $parsed->altitudeMeters,
                    'accuracy' => $parsed->accuracyMeters,
                    'status_metadata' => $parsed->statusMetadata,
                    'normalized_metadata' => [
                        ...$parsed->normalizedMetadata,
                        'device_identity' => $parsed->deviceIdentity,
                    ],
                ]);

                $rawPayload->forceFill([
                    'tenant_id' => $tracker->tenant_id,
                    'tracker_device_id' => $tracker->id,
                    'processing_status' => 'normalized',
                    'rejection_reason' => null,
                    'metadata' => [
                        ...($rawPayload->metadata ?? []),
                        'diagnostics' => [
                            'device_identity' => $parsed->deviceIdentity,
                            'normalized_event_id' => $event->id,
                            'parser_contract_key' => $contract->key(),
                            'parser_contract_version' => $contract->version(),
                        ],
                    ],
                ])->save();

                $this->trackerStates->markLive($tracker, $event);

                $this->audit('raw_payload.normalized', $rawPayload, [
                    'normalized_event_id' => $event->id,
                    'tracker_device_id' => $tracker->id,
                ], $actorId);

                return new IngestionOutcome($rawPayload->refresh(), $event);
            });

            event(new TrackerLocationUpdated($outcome->event->loadMissing('trackerDevice')));

            $this->logRawPayload('normalized', $outcome->rawPayload, [
                'normalized_event_id' => $outcome->event?->id,
                'tracker_device_id' => $outcome->rawPayload->tracker_device_id,
                'tenant_id' => $outcome->rawPayload->tenant_id,
            ]);

            return $outcome;
        } catch (IngestionRejected $exception) {
            return $this->reject($rawPayload, $exception, $actorId);
        }
    }

    private function resolveTracker(string $deviceIdentity, string $contractKey, int $contractVersion): TrackerDevice
    {
        $tracker = TrackerDevice::withoutGlobalScopes()
            ->where('contract_key', $contractKey)
            ->where('contract_version', $contractVersion)
            ->where('metadata->device_identity', $deviceIdentity)
            ->first();

        if (! $tracker) {
            throw new IngestionRejected('Tracker device could not be resolved for this contract identity.', 'deviceIdentity', [
                'device_identity' => $deviceIdentity,
                'parser_contract_key' => $contractKey,
                'parser_contract_version' => $contractVersion,
            ]);
        }

        return $tracker;
    }

    private function reject(RawPayload $rawPayload, IngestionRejected $exception, ?int $actorId = null): IngestionOutcome
    {
        $rawPayload->forceFill([
            'processing_status' => 'rejected',
            'rejection_reason' => $exception->getMessage(),
            'metadata' => [
                ...($rawPayload->metadata ?? []),
                'diagnostics' => [
                    'failed_field' => $exception->failedField,
                    'reason' => $exception->getMessage(),
                    ...$exception->diagnostics,
                ],
            ],
        ])->save();

        $this->audit('raw_payload.rejected', $rawPayload, [
            'failed_field' => $exception->failedField,
            'reason' => $exception->getMessage(),
        ], $actorId);

        $this->logRawPayload('rejected', $rawPayload, [
            'failed_field' => $exception->failedField,
            'reason' => $exception->getMessage(),
            'diagnostics' => $exception->diagnostics,
            'actor_id' => $actorId,
        ]);

        return new IngestionOutcome($rawPayload->refresh());
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logRawPayload(string $event, RawPayload $rawPayload, array $context = []): void
    {
        $body = $context['body'] ?? $rawPayload->body_content;

        if (! is_string($body)) {
            $body = '';
        }

        Log::info("Raw payload {$event}.", [
            'raw_payload_id' => $rawPayload->id,
            'event' => $event,
            'processing_status' => $rawPayload->processing_status,
            'tenant_id' => $rawPayload->tenant_id,
            'tracker_device_id' => $rawPayload->tracker_device_id,
            'parser_contract_key' => $rawPayload->parser_contract_key,
            'parser_contract_version' => $rawPayload->parser_contract_version,
            'body_content_type' => $rawPayload->body_content_type,
            'body_bytes' => strlen($body),
            'body_sha256' => hash('sha256', $body),
            ...$context,
            'body' => $body,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $action, RawPayload $rawPayload, array $metadata = [], ?int $actorId = null): void
    {
        AuditLog::query()->create([
            'tenant_id' => $rawPayload->tenant_id,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $rawPayload::class,
            'subject_id' => $rawPayload->id,
            'metadata' => [
                'parser_contract_key' => $rawPayload->parser_contract_key,
                'parser_contract_version' => $rawPayload->parser_contract_version,
                ...array_filter($metadata, fn ($value) => $value !== null),
            ],
        ]);
    }
}
