<?php

return [
    'auth' => [
        'magic_link_expiration_minutes' => env('MTRACK_MAGIC_LINK_EXPIRATION_MINUTES', 15),
        'mobile_refresh_token_days' => env('MTRACK_MOBILE_REFRESH_TOKEN_DAYS', 60),
    ],

    'tenancy' => [
        'raw_payload_retention_presets' => [30, 90, 180, 365],
        'permission_levels' => ['hide', 'view', 'edit'],
    ],

    'modules' => [
        'admin' => [
            'dashboard',
            'live',
            'playback',
            'events',
            'devices',
            'geofence',
            'routes',
            'customers',
            'payments',
            'logs',
        ],
        'customer' => [
            'dashboard',
            'live',
            'playback',
            'events',
            'my_fleets',
            'geofence',
            'analysis',
            'routes',
            'settings',
            'billing',
            'audit_log',
        ],
    ],
];
