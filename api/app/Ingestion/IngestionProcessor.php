<?php

namespace App\Ingestion;

use App\Ingestion\Data\IngestionOutcome;
use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Exceptions\IngestionRejected;
use App\Models\AuditLog;
use App\Models\NormalizedLocationEvent;
use App\Models\RawPayload;
use App\Models\TrackerDevice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IngestionProcessor
{
    public function __construct(private readonly ContractRegistry $contracts) {}

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

            return DB::transaction(function () use ($rawPayload, $tracker, $contract, $parsed, $actorId): IngestionOutcome {
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

                $tracker->forceFill([
                    'status' => $parsed->statusMetadata['device_status'] ?? $tracker->status,
                    'last_event_id' => $event->id,
                    'last_seen_at' => $event->event_timestamp,
                ])->save();

                $this->audit('raw_payload.normalized', $rawPayload, [
                    'normalized_event_id' => $event->id,
                    'tracker_device_id' => $tracker->id,
                ], $actorId);

                return new IngestionOutcome($rawPayload->refresh(), $event);
            });
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

        return new IngestionOutcome($rawPayload->refresh());
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
