<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'billing_status',
        'raw_payload_retention_days',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function fleetGroups(): HasMany
    {
        return $this->hasMany(FleetGroup::class);
    }

    public function trackerDevices(): HasMany
    {
        return $this->hasMany(TrackerDevice::class);
    }

    public function rawPayloads(): HasMany
    {
        return $this->hasMany(RawPayload::class);
    }

    public function normalizedLocationEvents(): HasMany
    {
        return $this->hasMany(NormalizedLocationEvent::class);
    }

    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class);
    }

    public function alertEvents(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }

    public function licenseAllocations(): HasMany
    {
        return $this->hasMany(LicenseAllocation::class);
    }

    public function licenseRequests(): HasMany
    {
        return $this->hasMany(LicenseRequest::class);
    }

    public function paymentSlips(): HasMany
    {
        return $this->hasMany(PaymentSlip::class);
    }
}
