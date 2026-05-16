<?php

namespace Tests\Feature\Tracker;

use App\Enums\Status;
use App\Models\Client;
use App\Models\ContactPerson;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverableCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_deliverable_with_contacts(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $client->id,
        ]);
        $c1 = ContactPerson::factory()->create(['client_id' => $client->id]);
        $c2 = ContactPerson::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->post('/deliverables', [
            'project_id' => $project->id,
            'name' => 'Legacy Score Interface',
            'description' => 'Live in staging, signed off by client',
            'target_days' => 2.0,
            'status' => 'A',
            'moscow' => 'M',
            'contact_ids' => [$c1->id, $c2->id],
        ]);

        $deliverable = Deliverable::where('name', 'Legacy Score Interface')->firstOrFail();
        $response->assertRedirect(route('deliverables.show', $deliverable));

        // Form took days (2.0); storage is hours (16.0).
        $this->assertEquals(16.0, (float) $deliverable->target_hours);
        $this->assertSame(Status::Amber, $deliverable->status);
        $this->assertCount(2, $deliverable->contactPersons);
    }

    public function test_target_days_must_be_in_half_day_increments(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->from('/deliverables/create')
            ->post('/deliverables', [
                'project_id' => $project->id,
                'name' => 'Bad value',
                'target_days' => 1.3, // not a 0.5 multiple
                'status' => 'R',
            ]);

        $response->assertSessionHasErrors('target_days');
    }

    public function test_contact_must_belong_to_projects_client(): void
    {
        $user = User::factory()->create();
        $clientA = Client::factory()->create(['owner_id' => $user->id]);
        $clientB = Client::factory()->create(['owner_id' => $user->id]);
        $projectA = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $clientA->id,
        ]);
        $contactInB = ContactPerson::factory()->create(['client_id' => $clientB->id]);

        $response = $this->actingAs($user)
            ->from('/deliverables/create')
            ->post('/deliverables', [
                'project_id' => $projectA->id,
                'name' => 'Wrong contact',
                'target_days' => 1.0,
                'status' => 'R',
                'contact_ids' => [$contactInB->id],
            ]);

        $response->assertSessionHasErrors('contact_ids.0');
    }

    public function test_user_can_update_contacts_via_sync(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $client->id,
        ]);
        $c1 = ContactPerson::factory()->create(['client_id' => $client->id]);
        $c2 = ContactPerson::factory()->create(['client_id' => $client->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        $deliverable->contactPersons()->attach([$c1->id]);

        $this->actingAs($user)->put("/deliverables/{$deliverable->id}", [
            'project_id' => $project->id,
            'name' => $deliverable->name,
            'target_days' => $deliverable->target_days, // derived days accessor
            'status' => 'R',
            'contact_ids' => [$c2->id], // swap c1 → c2
        ]);

        $deliverable->refresh()->load('contactPersons');
        $this->assertCount(1, $deliverable->contactPersons);
        $this->assertSame($c2->id, $deliverable->contactPersons->first()->id);
    }

    public function test_user_cannot_create_deliverable_for_another_users_project(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $projectOfB = Project::factory()->create(['owner_id' => $userB->id]);

        $response = $this->actingAs($userA)
            ->from('/deliverables/create')
            ->post('/deliverables', [
                'project_id' => $projectOfB->id,
                'name' => 'Should fail',
                'target_days' => 1.0,
                'status' => 'R',
            ]);

        $response->assertSessionHasErrors('project_id');
    }

    public function test_user_cannot_view_another_users_deliverable(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $projectOfB = Project::factory()->create(['owner_id' => $userB->id]);
        $deliverableOfB = Deliverable::factory()->create(['project_id' => $projectOfB->id]);

        $response = $this->actingAs($userA)->get("/deliverables/{$deliverableOfB->id}");

        $response->assertForbidden();
    }
}
