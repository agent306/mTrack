<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\DefaultAccessProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(DefaultAccessProvisioner $defaultAccess): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();
        $email = mb_strtolower($googleUser->getEmail());

        $defaultAccess->provisionForEmail($email, $googleUser->getName(), 'google');

        $user = User::query()
            ->with('tenant')
            ->where('email', $email)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('tenant_id')
                    ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('status', 'active'));
            })
            ->first();

        if (! $user) {
            return redirect()
                ->route('login')
                ->with('error', 'That Google account is not provisioned for mTrack.');
        }

        $user->forceFill([
            'auth_provider' => 'google',
            'google_id' => $googleUser->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
