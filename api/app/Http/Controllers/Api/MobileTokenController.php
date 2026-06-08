<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileRefreshToken;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileTokenController extends Controller
{
    public function refresh(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $token = MobileRefreshToken::query()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->where('token_hash', hash('sha256', $validated['refresh_token']))
            ->first();

        abort_unless($token, 401);

        $token->forceFill(['last_used_at' => now()])->save();
        $accessToken = $token->user->createToken($validated['device_name'] ?? 'mTrack mobile')->plainTextToken;

        $audit->record($token->user, 'mobile_token.refreshed', $token, $token->user->tenant_id, [
            'device_name' => $validated['device_name'] ?? 'mTrack mobile',
            'refresh_token_id' => $token->id,
        ], $request);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
        ]);
    }

    public function destroy(Request $request, AuditLogger $audit): JsonResponse
    {
        $tokenId = $request->user()?->currentAccessToken()?->id;
        $request->user()?->currentAccessToken()?->delete();

        $audit->record($request->user(), 'mobile_token.revoked', null, $request->user()?->tenant_id, [
            'access_token_id' => $tokenId,
        ], $request);

        return response()->json(['status' => 'revoked']);
    }

    public static function issueRefreshToken(int $userId, string $name = 'mTrack mobile'): string
    {
        $plainTextToken = Str::random(80);

        MobileRefreshToken::query()->create([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $plainTextToken),
            'name' => $name,
            'expires_at' => now()->addDays(config('mtrack.auth.mobile_refresh_token_days')),
        ]);

        return $plainTextToken;
    }
}
