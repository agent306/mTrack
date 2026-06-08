<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenants.{tenantId}.operations', function ($user, int $tenantId) {
    return (int) $user->tenant_id === $tenantId;
});
