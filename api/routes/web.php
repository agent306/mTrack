<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Customer\CustomerController;
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

    Route::redirect('/customer', '/customer/dashboard')->name('customer.index');
    Route::get('/customer/exports/{report}', [CustomerController::class, 'exportCsv'])
        ->name('customer.exports.show');
    Route::post('/customer/fleet-groups', [CustomerController::class, 'storeFleetGroup'])
        ->name('customer.fleet-groups.store');
    Route::put('/customer/trackers/{trackerDevice}', [CustomerController::class, 'updateTracker'])
        ->name('customer.trackers.update');
    Route::post('/customer/geofence', [CustomerController::class, 'storeGeofence'])
        ->name('customer.geofence.store');
    Route::put('/customer/geofence/{geofence}', [CustomerController::class, 'updateGeofence'])
        ->name('customer.geofence.update');
    Route::post('/customer/billing/license-requests', [CustomerController::class, 'storeLicenseRequest'])
        ->name('customer.billing.license-requests.store');
    Route::post('/customer/billing/payment-slips', [CustomerController::class, 'uploadPaymentSlip'])
        ->name('customer.billing.payment-slips.store');
    Route::post('/customer/settings', [CustomerController::class, 'storeSetting'])
        ->name('customer.settings.store');
    Route::post('/customer/settings/api-token', [CustomerController::class, 'regenerateApiToken'])
        ->name('customer.settings.api-token');
    Route::get('/customer/{module}', [CustomerController::class, 'show'])
        ->name('customer.show');

    Route::redirect('/admin', '/admin/dashboard')->name('admin.index');
    Route::get('/admin/exports/{report}', [AdminController::class, 'exportCsv'])
        ->name('admin.exports.show');
    Route::get('/admin/{module}', [AdminController::class, 'show'])->name('admin.show');
    Route::post('/admin/payments/{paymentSlip}/approve', [AdminController::class, 'approvePayment'])
        ->name('admin.payments.approve');
    Route::post('/admin/payments/{paymentSlip}/reject', [AdminController::class, 'rejectPayment'])
        ->name('admin.payments.reject');
    Route::post('/admin/customers/{tenant}/approve', [AdminController::class, 'approveCustomer'])
        ->name('admin.customers.approve');
    Route::post('/admin/customers/{tenant}/block', [AdminController::class, 'blockCustomer'])
        ->name('admin.customers.block');
    Route::post('/admin/devices/{trackerDevice}/assign', [AdminController::class, 'assignTracker'])
        ->name('admin.devices.assign');
    Route::post('/admin/devices/assign-discovered', [AdminController::class, 'assignDiscovered'])
        ->name('admin.devices.assign-discovered');
    Route::post('/admin/geofence', [AdminController::class, 'storeGeofence'])
        ->name('admin.geofence.store');
    Route::put('/admin/geofence/{geofence}', [AdminController::class, 'updateGeofence'])
        ->name('admin.geofence.update');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
