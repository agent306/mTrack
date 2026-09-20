<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Tracking\DeviceGateway;
use Illuminate\Http\Request;

class DeviceGatewayController extends Controller
{
    public function store(Request $request, DeviceGateway $gateway)
    {
        $secret = config('tracker.gateway_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32
            && hash_equals($secret, (string) $request->bearerToken()), 401);
        $packet = $request->validate([
            'imei' => ['required', 'regex:/^\d{15}$/'],
            'packet_hex' => ['required', 'string', 'max:8192', 'regex:/^(?:[a-f0-9]{2})+$/'],
            'protocol' => ['required', 'integer', 'between:0,255'],
            'iccid' => ['nullable', 'regex:/^\d{18,22}$/'],
            'location' => ['nullable', 'array:timestamp,latitude,longitude,speedKmh,heading,ignition'],
            'location.timestamp' => ['required_with:location', 'date', 'before_or_equal:'.now()->addMinutes(10)->toIso8601String()],
            'location.latitude' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required_with:location', 'numeric', 'between:-180,180'],
            'location.speedKmh' => ['nullable', 'numeric', 'between:0,1000'],
            'location.heading' => ['nullable', 'numeric', 'between:0,360'],
            'location.ignition' => ['nullable', 'boolean'],
        ]);
        $gateway->receive($packet);
        return response()->json(['status' => 'stored']);
    }
}
