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

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_project(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->post('/projects', [
            'client_id' => $client->id,
            'name' => 'Carbon Back-end',
            'description' => 'Refactor across markets',
            'moscow' => 'M',
        ]);

        $project = Project::where('name', 'Carbon Back-end')->firstOrFail();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame($user->id, $project->owner_id);
        $this->assertSame($client->id, $project->client_id);
    }

    public function test_create_rejects_other_users_client(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $clientOfB = Client::factory()->create(['owner_id' => $userB->id]);

        $response = $this->actingAs($userA)
            ->from('/projects/create')
            ->post('/projects', [
                'client_id' => $clientOfB->id,
                'name' => 'Should fail',
            ]);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_create_rejects_contact_that_belongs_to_a_different_client(): void
    {
        $user = User::factory()->create();
        $clientA = Client::factory()->create(['owner_id' => $user->id]);
        $clientB = Client::factory()->create(['owner_id' => $user->id]);
        $contactInB = ContactPerson::factory()->create(['client_id' => $clientB->id]);

        $response = $this->actingAs($user)
            ->from('/projects/create')
            ->post('/projects', [
                'client_id' => $clientA->id,
                'name' => 'Wrong contact',
                'responsible_contact_id' => $contactInB->id,
            ]);

        $response->assertSessionHasErrors('responsible_contact_id');
    }

    public function test_project_status_is_derived_from_deliverables(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);

        // No deliverables → R (empty default)
        $this->assertSame(Status::Red, $project->fresh()->status);

        // One Green deliverable → G
        Deliverable::factory()->create(['project_id' => $project->id, 'status' => Status::Green]);
        $this->assertSame(Status::Green, $project->fresh()->status);

        // Add an Amber → A wins over G
        Deliverable::factory()->create(['project_id' => $project->id, 'status' => Status::Amber]);
        $this->assertSame(Status::Amber, $project->fresh()->status);

        // Add a Blocked → B wins over A and G
        Deliverable::factory()->create(['project_id' => $project->id, 'status' => Status::Blocked]);
        $this->assertSame(Status::Blocked, $project->fresh()->status);

        // Add a Red → R wins over everything
        Deliverable::factory()->create(['project_id' => $project->id, 'status' => Status::Red]);
        $this->assertSame(Status::Red, $project->fresh()->status);
    }

    public function test_user_cannot_see_another_users_project(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $projectOfB = Project::factory()->create(['owner_id' => $userB->id]);

        $response = $this->actingAs($userA)->get("/projects/{$projectOfB->id}");

        $response->assertForbidden();
    }

    public function test_deleting_project_cascades_to_deliverables(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)->delete("/projects/{$project->id}");

        $this->assertModelMissing($project);
        $this->assertModelMissing($deliverable);
    }
}
