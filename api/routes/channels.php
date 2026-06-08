<?php

use App\Models\TrackerDevice;
use App\Support\Authorization\PermissionMatrix;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenants.{tenantId}.operations', function ($user, int $tenantId) {
    return (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('tenants.{tenantId}.trackers', function ($user, int $tenantId) {
    return $user->isPlatformAdmin()
        || ((int) $user->tenant_id === $tenantId
            && $user->hasPermission('live', PermissionMatrix::VIEW));
});

Broadcast::channel('tenants.{tenantId}.trackers.{trackerDeviceId}', function ($user, int $tenantId, int $trackerDeviceId) {
    if ($user->isPlatformAdmin()) {
        return true;
    }

    if ((int) $user->tenant_id !== $tenantId) {
        return false;
    }

    $trackerDevice = TrackerDevice::withoutGlobalScopes()
        ->where('tenant_id', $tenantId)
        ->whereKey($trackerDeviceId)
        ->first();

    return $trackerDevice !== null
        && $user->hasPermission('live', PermissionMatrix::VIEW, trackerId: $trackerDevice->id);
});
