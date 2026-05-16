<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public registration is intentionally disabled. All accounts are created
 * by an admin via /admin/users. These tests confirm the routes really are
 * gone (i.e. nobody can sneak in via a stray HTTP request).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_register_route_does_not_exist(): void
    {
        $response = $this->get('/register');
        $response->assertNotFound();
    }

    public function test_post_register_route_does_not_exist(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
    }
}
