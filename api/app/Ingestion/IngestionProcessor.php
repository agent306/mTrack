<?php

namespace App\Ingestion;

use App\Events\TrackerLocationUpdated;
use App\Ingestion\Contracts\ParserContract;
use App\Ingestion\Data\IngestionOutcome;
use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\Data\ParsedLocation;
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

    public function processIncoming(IngestionPayload $payload, ?string $contractKey = null): IngestionOutcome
    {
        $contract = $contractKey === null ? null : $this->contracts->resolve($contractKey);

        $rawPayload = RawPayload::query()->create([
            'parser_contract_key' => $contract?->key(),
            'parser_contract_version' => $contract?->version(),
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
            [$contract, $parsed, $tracker] = $this->parseAndResolveTracker($rawPayload);

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
        $tracker = $this->findTracker($deviceIdentity, $contractKey, $contractVersion);

        if (! $tracker) {
            throw new IngestionRejected('Tracker device could not be resolved for this contract identity.', 'deviceIdentity', [
                'device_identity' => $deviceIdentity,
                'parser_contract_key' => $contractKey,
                'parser_contract_version' => $contractVersion,
            ]);
        }

        return $tracker;
    }

    private function findTracker(string $deviceIdentity, string $contractKey, int $contractVersion): ?TrackerDevice
    {
        return TrackerDevice::withoutGlobalScopes()
            ->where('contract_key', $contractKey)
            ->where('contract_version', $contractVersion)
            ->where('metadata->device_identity', $deviceIdentity)
            ->first();
    }

    /**
     * @return array{0: ParserContract, 1: ParsedLocation, 2: TrackerDevice}
     */
    private function parseAndResolveTracker(RawPayload $rawPayload): array
    {
        $payload = $this->payloadFromRaw($rawPayload);

        if (is_string($rawPayload->parser_contract_key)) {
            $contract = $this->contracts->resolve($rawPayload->parser_contract_key);
            $parsed = $contract->parse($payload);

            return [$contract, $parsed, $this->resolveTracker($parsed->deviceIdentity, $contract->key(), $contract->version())];
        }

        return $this->detectContractAndTracker($rawPayload, $payload);
    }

    /**
     * @return array{0: ParserContract, 1: ParsedLocation, 2: TrackerDevice}
     */
    private function detectContractAndTracker(RawPayload $rawPayload, IngestionPayload $payload): array
    {
        $parsedCandidates = [];
        $rejections = [];
        $firstParsedContract = null;
        $firstParsedLocation = null;

        foreach ($this->contracts->all() as $contract) {
            try {
                $parsed = $contract->parse($payload);
            } catch (IngestionRejected $exception) {
                $rejections[$contract->key()] = [
                    'failed_field' => $exception->failedField,
                    'reason' => $exception->getMessage(),
                ];

                continue;
            }

            $parsedCandidates[$contract->key()] = [
                'device_identity' => $parsed->deviceIdentity,
                'parser_contract_version' => $contract->version(),
            ];
            $firstParsedContract ??= $contract;
            $firstParsedLocation ??= $parsed;

            $tracker = $this->findTracker($parsed->deviceIdentity, $contract->key(), $contract->version());

            if ($tracker) {
                $rawPayload->forceFill([
                    'parser_contract_key' => $contract->key(),
                    'parser_contract_version' => $contract->version(),
                    'metadata' => [
                        ...($rawPayload->metadata ?? []),
                        'contract_detection' => [
                            'mode' => 'auto',
                            'matched_contract_key' => $contract->key(),
                            'device_identity' => $parsed->deviceIdentity,
                        ],
                    ],
                ])->save();

                return [$contract, $parsed, $tracker];
            }
        }

        if ($firstParsedContract && $firstParsedLocation) {
            $rawPayload->forceFill([
                'parser_contract_key' => $firstParsedContract->key(),
                'parser_contract_version' => $firstParsedContract->version(),
            ])->save();

            throw new IngestionRejected('Tracker device could not be resolved for this detected contract identity.', 'deviceIdentity', [
                'device_identity' => $firstParsedLocation->deviceIdentity,
                'parser_contract_key' => $firstParsedContract->key(),
                'parser_contract_version' => $firstParsedContract->version(),
                'contract_detection' => [
                    'mode' => 'auto',
                    'parsed_candidates' => $parsedCandidates,
                ],
            ]);
        }

        $tracker = $this->detectTrackerFromPayloadIdentity($payload);

        if ($tracker && is_string($tracker->contract_key)) {
            $contract = $this->contracts->resolve($tracker->contract_key);

            $rawPayload->forceFill([
                'parser_contract_key' => $contract->key(),
                'parser_contract_version' => $contract->version(),
            ])->save();

            $parsed = $contract->parse($payload);

            return [$contract, $parsed, $tracker];
        }

        throw $this->contractDetectionRejected($rejections);
    }

    private function contractDetectionRejected(array $rejections): IngestionRejected
    {
        $fields = collect($rejections)
            ->pluck('failed_field')
            ->filter()
            ->unique()
            ->values();

        if ($fields->count() === 1) {
            $rejection = collect($rejections)->firstWhere('failed_field', $fields->first());

            return new IngestionRejected((string) $rejection['reason'], (string) $fields->first(), [
                'contract_detection' => [
                    'mode' => 'auto',
                    'rejections' => $rejections,
                ],
            ]);
        }

        return new IngestionRejected('Parser contract could not be detected from this payload.', 'parser_contract_key', [
            'contract_detection' => [
                'mode' => 'auto',
                'rejections' => $rejections,
            ],
        ]);
    }

    private function detectTrackerFromPayloadIdentity(IngestionPayload $payload): ?TrackerDevice
    {
        $identities = collect([
            $payload->headers['x-device-id'] ?? null,
            $payload->headers['x-tracker-id'] ?? null,
        ]);

        $data = json_decode($payload->body, true);

        if (is_array($data)) {
            $identities = $identities->merge(collect([
                data_get($data, 'trackerId'),
                data_get($data, 'deviceId'),
                data_get($data, 'device.id'),
                data_get($data, 'imei'),
                data_get($data, 'deviceImei'),
                data_get($data, 'device.imei'),
                data_get($data, 'terminalPhone'),
                data_get($data, 'terminal_phone'),
                data_get($data, 'sim'),
                data_get($data, 'mmsi'),
                data_get($data, 'ais.mmsi'),
                data_get($data, 'vessel.mmsi'),
            ]));
        }

        foreach ($identities->filter(fn ($identity): bool => is_scalar($identity) && trim((string) $identity) !== '')->unique() as $identity) {
            $tracker = TrackerDevice::withoutGlobalScopes()
                ->where('metadata->device_identity', trim((string) $identity))
                ->whereNotNull('contract_key')
                ->whereNotNull('contract_version')
                ->first();

            if ($tracker) {
                return $tracker;
            }
        }

        return null;
    }

    private function payloadFromRaw(RawPayload $rawPayload): IngestionPayload
    {
        return new IngestionPayload(
            body: $rawPayload->body_content,
            contentType: $rawPayload->body_content_type,
            headers: $rawPayload->headers ?? [],
            receivedAt: $rawPayload->received_at,
        );
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
