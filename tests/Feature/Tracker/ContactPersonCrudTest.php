<?php

namespace Tests\Feature\Tracker;

use App\Models\Client;
use App\Models\ContactPerson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPersonCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_contact_to_their_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->post("/clients/{$client->id}/contacts", [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'role_title' => 'CTO',
        ]);

        $response->assertRedirect(route('clients.show', $client));

        $contact = ContactPerson::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame($client->id, $contact->client_id);
    }

    public function test_user_cannot_add_contact_to_another_users_client(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $clientOfB = Client::factory()->create(['owner_id' => $userB->id]);

        $response = $this->actingAs($userA)->post("/clients/{$clientOfB->id}/contacts", [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, $clientOfB->contactPersons()->count());
    }

    public function test_user_can_update_contact(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $contact = ContactPerson::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user)->put("/clients/{$client->id}/contacts/{$contact->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);

        $this->assertSame('Updated', $contact->fresh()->first_name);
    }

    public function test_contact_url_must_match_parent_client(): void
    {
        // Tampering: trying to edit contact X under client Y where X belongs
        // to a different client. Even though policy might pass, the
        // controller's ensureBelongsToClient check 404s.
        $user = User::factory()->create();
        $clientA = Client::factory()->create(['owner_id' => $user->id]);
        $clientB = Client::factory()->create(['owner_id' => $user->id]);
        $contactInA = ContactPerson::factory()->create(['client_id' => $clientA->id]);

        $response = $this->actingAs($user)->get("/clients/{$clientB->id}/contacts/{$contactInA->id}/edit");

        $response->assertNotFound();
    }

    public function test_user_can_delete_contact(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $contact = ContactPerson::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user)->delete("/clients/{$client->id}/contacts/{$contact->id}");

        $this->assertModelMissing($contact);
    }

    public function test_deleting_client_cascades_to_contacts(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $contact = ContactPerson::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user)->delete("/clients/{$client->id}");

        $this->assertModelMissing($contact);
    }
}
