<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Ingestion\Data\IngestionPayload;
use App\Ingestion\IngestionProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class IngestionController extends Controller
{
    public function store(Request $request, IngestionProcessor $ingestion): JsonResponse
    {
        [$body, $contentType] = $this->payloadBody($request);

        try {
            $outcome = $ingestion->processIncoming(new IngestionPayload(
                body: $body,
                contentType: $contentType,
                headers: $this->diagnosticHeaders($request),
                receivedAt: Carbon::now(),
            ));
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'rejected',
                'reason' => $exception->getMessage(),
            ], 404);
        }

        if (! $outcome->accepted()) {
            $status = str_contains((string) $outcome->rawPayload->rejection_reason, 'byte limit') ? 413 : 422;

            return response()->json([
                'status' => 'rejected',
                'raw_payload_id' => $outcome->rawPayload->id,
                'reason' => $outcome->rawPayload->rejection_reason,
                'diagnostics' => $outcome->rawPayload->metadata['diagnostics'] ?? [],
            ], $status);
        }

        return response()->json([
            'status' => 'normalized',
            'raw_payload_id' => $outcome->rawPayload->id,
            'normalized_location_event_id' => $outcome->event?->id,
            'tracker_device_id' => $outcome->event?->tracker_device_id,
        ], 201);
    }

    /**
     * @return array<string, string|null>
     */
    private function diagnosticHeaders(Request $request): array
    {
        $headers = collect(config('mtrack.ingestion.diagnostic_headers'))
            ->mapWithKeys(fn (string $header): array => [$header => $request->headers->get($header)])
            ->filter(fn (?string $value): bool => $value !== null)
            ->all();

        return [
            ...$headers,
            'x-ingest-method' => $request->method(),
            'x-ingest-query' => $request->getQueryString(),
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function payloadBody(Request $request): array
    {
        $body = $request->getContent();

        if ($body !== '') {
            return [$body, $request->headers->get('content-type')];
        }

        $payload = $request->query('payload');

        if (is_scalar($payload)) {
            return [(string) $payload, $request->headers->get('content-type') ?? 'text/plain'];
        }

        $query = $request->query->all();

        if ($query !== []) {
            return [json_encode($query, JSON_THROW_ON_ERROR), 'application/json'];
        }

        return ['', $request->headers->get('content-type')];
    }
}
