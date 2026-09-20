<?php

return [
    'host' => env('TRACKER_PUBLIC_HOST', '124.195.201.14'),
    'port' => (int) env('TRACKER_PUBLIC_PORT', 5023),
    'gateway_secret' => env('TRACKER_GATEWAY_SECRET'),
];
