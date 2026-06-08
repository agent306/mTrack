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
    public function store(Request $request, string $contractKey, IngestionProcessor $ingestion): JsonResponse
    {
        try {
            $outcome = $ingestion->processIncoming($contractKey, new IngestionPayload(
                body: $request->getContent(),
                contentType: $request->headers->get('content-type'),
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
        return collect(config('mtrack.ingestion.diagnostic_headers'))
            ->mapWithKeys(fn (string $header): array => [$header => $request->headers->get($header)])
            ->filter(fn (?string $value): bool => $value !== null)
            ->all();
    }
}
