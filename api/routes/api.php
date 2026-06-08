<?php

use App\Http\Controllers\Api\MobileTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/mobile/refresh', [MobileTokenController::class, 'refresh'])
    ->middleware('throttle:10,1')
    ->name('api.auth.mobile.refresh');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', fn (Request $request) => $request->user()->loadMissing('tenant', 'roles'));

    Route::post('/auth/mobile/logout', [MobileTokenController::class, 'destroy'])
        ->name('api.auth.mobile.logout');
});
