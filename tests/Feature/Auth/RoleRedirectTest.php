<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each role has its own post-login landing page. These tests pin that
 * behaviour so a careless refactor of AuthenticatedSessionController
 * can't quietly route everyone back to a single shared dashboard.
 */
class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lands_on_admin_users_after_login(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.users.index'));
    }

    public function test_user_lands_on_dashboard_after_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_viewer_lands_on_viewer_dashboard_after_login(): void
    {
        $viewer = User::factory()->viewer()->create();

        $response = $this->post('/login', [
            'email' => $viewer->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('viewer.dashboard'));
    }

    public function test_admin_hitting_dashboard_is_redirected_to_their_landing_route(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('admin.users.index'));
    }

    public function test_user_hitting_admin_users_is_redirected_to_their_landing_route(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_viewer_hitting_admin_users_is_redirected_to_their_landing_route(): void
    {
        $viewer = User::factory()->viewer()->create();

        $response = $this->actingAs($viewer)->get('/admin/users');

        $response->assertRedirect(route('viewer.dashboard'));
    }

    public function test_guest_hitting_dashboard_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }
}
