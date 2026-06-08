<?php

use App\Ingestion\Contracts\DemoJsonLocationContract;

$adminModules = [
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
    'users-roles',
    'settings',
];

$customerModules = [
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
];

return [
    'auth' => [
        'magic_link_expiration_minutes' => env('MTRACK_MAGIC_LINK_EXPIRATION_MINUTES', 15),
        'mobile_refresh_token_days' => env('MTRACK_MOBILE_REFRESH_TOKEN_DAYS', 60),
    ],

    'tenancy' => [
        'raw_payload_retention_presets' => [30, 90, 180, 365],
        'permission_levels' => ['hide', 'view', 'edit'],
    ],

    'ingestion' => [
        'max_payload_bytes' => env('MTRACK_INGESTION_MAX_PAYLOAD_BYTES', 65536),
        'rate_limit_attempts' => env('MTRACK_INGESTION_RATE_LIMIT_ATTEMPTS', 120),
        'rate_limit_decay_minutes' => env('MTRACK_INGESTION_RATE_LIMIT_DECAY_MINUTES', 1),
        'diagnostic_headers' => [
            'content-type',
            'user-agent',
            'x-forwarded-for',
            'x-device-id',
            'x-tracker-id',
        ],
        'contracts' => [
            'demo-json' => DemoJsonLocationContract::class,
        ],
    ],

    'realtime' => [
        'stale_after_minutes' => env('MTRACK_STALE_AFTER_MINUTES', 30),
        'offline_after_minutes' => env('MTRACK_OFFLINE_AFTER_MINUTES', 120),
    ],

    'operations' => [
        'unresolved_raw_payload_retention_days' => env('MTRACK_UNRESOLVED_RAW_PAYLOAD_RETENTION_DAYS', 90),
        'backup_path' => env('MTRACK_BACKUP_PATH', 'app/backups'),
        'backup_storage_paths' => [
            'app/private',
            'app/public',
        ],
    ],

    'default_access' => [
        'tenants' => [
            'demo' => [
                'name' => 'mTrack Demo Fleet',
                'status' => 'active',
                'billing_status' => 'trial',
                'raw_payload_retention_days' => 90,
            ],
        ],
        'roles' => [
            'platform-admin' => [
                'tenant' => null,
                'name' => 'Platform Administrator',
                'slug' => 'platform-admin',
                'scope' => 'platform',
                'permissions' => [
                    'modules' => array_fill_keys($adminModules, 'edit'),
                ],
            ],
            'tenant-admin' => [
                'tenant' => 'demo',
                'name' => 'Tenant Administrator',
                'slug' => 'tenant-admin',
                'scope' => 'tenant',
                'permissions' => [
                    'modules' => array_fill_keys($customerModules, 'edit'),
                ],
            ],
        ],
        'users' => [
            [
                'tenant' => null,
                'name' => 'Ncodex',
                'email' => 'hello@nashath.dev',
                'roles' => ['platform-admin'],
            ],
            [
                'tenant' => null,
                'name' => 'Natthu',
                'email' => 'masigning@gmail.com',
                'roles' => ['platform-admin'],
            ],
            [
                'tenant' => 'demo',
                'name' => 'mTrack Operator',
                'email' => 'test@example.com',
                'roles' => ['tenant-admin'],
            ],
        ],
    ],

    'modules' => [
        'admin' => $adminModules,
        'customer' => $customerModules,
    ],
];
