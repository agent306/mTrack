<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Ingestion\IngestionProcessor;
use App\Models\RawPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReplayRawPayloadController extends Controller
{
    public function store(Request $request, RawPayload $rawPayload, IngestionProcessor $ingestion): JsonResponse
    {
        $validated = $request->validate([
            'contract_key' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $outcome = $ingestion->replay(
                rawPayload: $rawPayload,
                contractKey: $validated['contract_key'] ?? null,
                actorId: $request->user()?->id,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'blocked',
                'reason' => $exception->getMessage(),
            ], 409);
        }

        if (! $outcome->accepted()) {
            return response()->json([
                'status' => 'rejected',
                'raw_payload_id' => $outcome->rawPayload->id,
                'reason' => $outcome->rawPayload->rejection_reason,
                'diagnostics' => $outcome->rawPayload->metadata['diagnostics'] ?? [],
            ], 422);
        }

        return response()->json([
            'status' => 'normalized',
            'raw_payload_id' => $outcome->rawPayload->id,
            'normalized_location_event_id' => $outcome->event?->id,
            'tracker_device_id' => $outcome->event?->tracker_device_id,
        ], 201);
    }
}
