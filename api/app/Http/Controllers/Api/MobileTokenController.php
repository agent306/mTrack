<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileRefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileTokenController extends Controller
{
    public function refresh(Request $request): JsonResponse
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

        return response()->json([
            'access_token' => $token->user->createToken($validated['device_name'] ?? 'mTrack mobile')->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

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
