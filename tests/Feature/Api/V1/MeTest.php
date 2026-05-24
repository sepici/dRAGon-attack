<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Support\ApiAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_receives_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'weekly_capacity_hours' => 40.0,
            'monthly_capacity_hours' => 160.0,
        ]);
        Sanctum::actingAs($user, [ApiAbility::READ_ALL]);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $user->id,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'user',
                'capacity' => [
                    'weekly_hours' => 40.0,
                    'weekly_days' => 5.0,
                    'monthly_hours' => 160.0,
                    'monthly_days' => 20.0,
                ],
            ],
        ]);
    }

    public function test_admin_token_returns_403(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, [ApiAbility::READ_ALL]);

        $response = $this->getJson('/api/v1/me');

        $response->assertForbidden();
        $response->assertJsonFragment(['message' => 'This token is not authorised for that role.']);
    }

    public function test_viewer_token_returns_403(): void
    {
        $viewer = User::factory()->viewer()->create();
        Sanctum::actingAs($viewer, [ApiAbility::READ_ALL]);

        $response = $this->getJson('/api/v1/me');

        $response->assertForbidden();
    }

    public function test_response_is_json_not_html_for_unauthenticated_api_request(): void
    {
        // Should return JSON 401, NOT a redirect to /login.
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
        $this->assertStringContainsString('json', strtolower($response->headers->get('content-type', '')));
    }

    public function test_unauthenticated_api_request_without_accept_header_still_returns_json(): void
    {
        // Plain curl without `Accept: application/json` should also get JSON,
        // not an HTML redirect to /login. The exception handler is configured
        // to coerce anything under /api/* to JSON.
        $response = $this->get('/api/v1/me');

        $response->assertStatus(401);
        $this->assertStringContainsString('json', strtolower($response->headers->get('content-type', '')));
    }
}
