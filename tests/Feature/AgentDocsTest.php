<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_agent_docs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/agent');

        $response->assertOk();
        $response->assertSeeText('Connect your AI');
        $response->assertSee(url('/api/v1/openapi.json'));
        $response->assertSee(url('/api/v1'));
    }

    public function test_admin_redirected_away_from_agent_docs(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/agent')->assertRedirect(route('admin.users.index'));
    }

    public function test_viewer_redirected_away_from_agent_docs(): void
    {
        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->get('/agent')->assertRedirect(route('viewer.dashboard'));
    }

    public function test_unauthenticated_visitor_redirected_to_login(): void
    {
        $this->get('/agent')->assertRedirect(route('login'));
    }
}
