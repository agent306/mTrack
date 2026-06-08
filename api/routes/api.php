<?php

use App\Http\Controllers\Api\IngestionController;
use App\Http\Controllers\Api\MobileTokenController;
use App\Http\Controllers\Api\ReplayRawPayloadController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TrackerLatestStateController;
use App\Http\Controllers\Api\UserRoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/mobile/refresh', [MobileTokenController::class, 'refresh'])
    ->middleware('throttle:10,1')
    ->name('api.auth.mobile.refresh');

Route::post('/ingest/{contractKey}', [IngestionController::class, 'store'])
    ->middleware('throttle:'.config('mtrack.ingestion.rate_limit_attempts').','.config('mtrack.ingestion.rate_limit_decay_minutes'))
    ->name('api.ingest.store');

Route::middleware(['auth:sanctum', 'active.user', 'tenant.resolve', 'active.tenant'])->group(function (): void {
    Route::get('/me', fn (Request $request) => $request->user()->loadMissing('tenant', 'roles'));

    Route::post('/auth/mobile/logout', [MobileTokenController::class, 'destroy'])
        ->name('api.auth.mobile.logout');

    Route::middleware('permission:settings,view')->group(function (): void {
        Route::apiResource('roles', RoleController::class);
    });

    Route::put('/users/{user}/roles', [UserRoleController::class, 'update'])
        ->middleware('permission:settings,edit')
        ->name('api.users.roles.update');

    Route::post('/raw-payloads/{rawPayload}/replay', [ReplayRawPayloadController::class, 'store'])
        ->middleware('permission:settings,edit')
        ->name('api.raw-payloads.replay');

    Route::get('/trackers/latest', [TrackerLatestStateController::class, 'index'])
        ->middleware('permission:live,view')
        ->name('api.trackers.latest.index');

    Route::get('/trackers/{trackerDevice}/latest', [TrackerLatestStateController::class, 'show'])
        ->middleware('permission:live,view')
        ->name('api.trackers.latest.show');
});
