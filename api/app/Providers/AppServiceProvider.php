<?php

namespace App\Providers;

use App\Models\AlertEvent;
use App\Models\FleetGroup;
use App\Models\Geofence;
use App\Models\LicenseAllocation;
use App\Models\LicensePlan;
use App\Models\LicenseRequest;
use App\Models\NormalizedLocationEvent;
use App\Models\PaymentSlip;
use App\Models\RawPayload;
use App\Models\Role;
use App\Models\TenantSetting;
use App\Models\TrackerDevice;
use App\Models\User;
use App\Policies\AlertEventPolicy;
use App\Policies\FleetGroupPolicy;
use App\Policies\GeofencePolicy;
use App\Policies\LicenseAllocationPolicy;
use App\Policies\LicensePlanPolicy;
use App\Policies\LicenseRequestPolicy;
use App\Policies\NormalizedLocationEventPolicy;
use App\Policies\PaymentSlipPolicy;
use App\Policies\RawPayloadPolicy;
use App\Policies\RolePolicy;
use App\Policies\TenantSettingPolicy;
use App\Policies\TrackerDevicePolicy;
use App\Policies\UserPolicy;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS','on');
        }

        Gate::policy(AlertEvent::class, AlertEventPolicy::class);
        Gate::policy(FleetGroup::class, FleetGroupPolicy::class);
        Gate::policy(Geofence::class, GeofencePolicy::class);
        Gate::policy(LicenseAllocation::class, LicenseAllocationPolicy::class);
        Gate::policy(LicensePlan::class, LicensePlanPolicy::class);
        Gate::policy(LicenseRequest::class, LicenseRequestPolicy::class);
        Gate::policy(NormalizedLocationEvent::class, NormalizedLocationEventPolicy::class);
        Gate::policy(PaymentSlip::class, PaymentSlipPolicy::class);
        Gate::policy(RawPayload::class, RawPayloadPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(TenantSetting::class, TenantSettingPolicy::class);
        Gate::policy(TrackerDevice::class, TrackerDevicePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
