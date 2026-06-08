<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_passwordless_sign_in_page(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_magic_link_is_sent_to_provisioned_active_user(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create([
            'email' => 'operator@example.com',
        ]);

        $this->post('/auth/magic-link', [
            'email' => 'operator@example.com',
        ])->assertSessionHas('status');

        Notification::assertSentTo($user, MagicLoginLink::class);
    }

    public function test_authenticated_user_can_open_foundation_shell(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }
}
