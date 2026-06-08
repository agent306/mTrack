<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\MagicLinkController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [MagicLinkController::class, 'create'])->name('login');
    Route::post('/auth/magic-link', [MagicLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('auth.magic-link.store');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::get('/auth/magic-link/{user}', [MagicLinkController::class, 'show'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('auth.magic-link.show');

Route::middleware(['auth', 'active.user', 'tenant.resolve', 'active.tenant'])->group(function (): void {
    Route::get('/dashboard', fn () => Inertia::render('Foundation/Overview', [
        'surface' => Auth::user()?->tenant_id ? 'customer' : 'platform',
    ]))->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
