<?php

namespace Tests\Feature\Tracker;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_client(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/clients', [
            'legal_name' => 'Acme Co',
            'email' => 'hello@acme.test',
            'phone' => '01234 567 890',
            'notes' => 'Long-time client',
        ]);

        $client = Client::where('legal_name', 'Acme Co')->firstOrFail();
        $response->assertRedirect(route('clients.show', $client));

        $this->assertSame($user->id, $client->owner_id);
        $this->assertSame('hello@acme.test', $client->email);
    }

    public function test_create_requires_legal_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/clients/create')
            ->post('/clients', ['legal_name' => '']);

        $response->assertSessionHasErrors('legal_name');
    }

    public function test_user_can_update_their_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->put("/clients/{$client->id}", [
            'legal_name' => 'Updated Name',
        ]);

        $this->assertSame('Updated Name', $client->fresh()->legal_name);
    }

    public function test_user_cannot_see_another_users_client(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $clientOfB = Client::factory()->create(['owner_id' => $userB->id]);

        $response = $this->actingAs($userA)->get("/clients/{$clientOfB->id}");

        $response->assertForbidden();
    }

    public function test_user_cannot_update_another_users_client(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $clientOfB = Client::factory()->create([
            'owner_id' => $userB->id,
            'legal_name' => 'B Co',
        ]);

        $response = $this->actingAs($userA)->put("/clients/{$clientOfB->id}", [
            'legal_name' => 'Hijacked',
        ]);

        $response->assertForbidden();
        $this->assertSame('B Co', $clientOfB->fresh()->legal_name);
    }

    public function test_user_can_delete_an_empty_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->delete("/clients/{$client->id}");

        $this->assertModelMissing($client);
    }

    public function test_user_cannot_delete_a_client_with_projects(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        Project::factory()->create(['owner_id' => $user->id, 'client_id' => $client->id]);

        $response = $this->actingAs($user)
            ->from(route('clients.edit', $client))
            ->delete("/clients/{$client->id}");

        $response->assertSessionHasErrors('delete');
        $this->assertModelExists($client);
    }

    public function test_admin_is_redirected_away_from_clients(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/clients');

        $response->assertRedirect(route('admin.users.index'));
    }

    public function test_viewer_is_redirected_away_from_clients_for_now(): void
    {
        // Viewer pages live under /viewer/* (planned). For now /clients is
        // gated by role:user middleware, which sends viewers home.
        $viewer = User::factory()->viewer()->create();

        $response = $this->actingAs($viewer)->get('/clients');

        $response->assertRedirect(route('viewer.dashboard'));
    }
}
