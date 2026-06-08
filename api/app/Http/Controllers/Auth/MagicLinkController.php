<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use App\Support\Auth\DefaultAccessProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class MagicLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/SignIn');
    }

    public function store(Request $request, DefaultAccessProvisioner $defaultAccess): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = mb_strtolower($validated['email']);
        $defaultAccess->provisionForEmail($email);

        $user = User::query()
            ->with('tenant')
            ->where('email', $email)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('tenant_id')
                    ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('status', 'active'));
            })
            ->first();

        if ($user) {
            $url = URL::temporarySignedRoute(
                'auth.magic-link.show',
                now()->addMinutes(config('mtrack.auth.magic_link_expiration_minutes')),
                ['user' => $user->id]
            );

            $user->notify(new MagicLoginLink($url));
        }

        return back()->with('status', 'If the account exists, a secure sign-in link has been sent.');
    }

    public function show(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature() && $user->canSignIn(), 403);

        $user->forceFill([
            'auth_provider' => 'magic_link',
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
