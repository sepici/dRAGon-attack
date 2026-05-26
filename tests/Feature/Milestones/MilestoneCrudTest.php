<?php

namespace Tests\Feature\Milestones;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Web-layer coverage for the Milestones tab — CRUD, ownership guards,
 * days→hours conversion, the scope_complete toggle, and the cross-project
 * guard on the Deliverable form (deliverable_id and milestone_id must
 * belong to the same project).
 */
class MilestoneCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_milestone_with_target_days(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($user)->post('/milestones', [
            'project_id' => $project->id,
            'name' => 'Phase 1 — Discovery',
            'description' => 'Define requirements and acceptance criteria',
            'target_days' => 5.0,
            'moscow' => 'M',
            'scope_complete' => '0',
        ]);

        $milestone = Milestone::where('name', 'Phase 1 — Discovery')->firstOrFail();
        $response->assertRedirect(route('milestones.show', $milestone));

        // Days (5.0) converted to hours (40.0) for storage.
        $this->assertEquals(40.0, (float) $milestone->target_hours);
        $this->assertSame($project->id, $milestone->project_id);
        $this->assertFalse((bool) $milestone->scope_complete);
    }

    public function test_user_can_create_a_milestone_without_target_days(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/milestones', [
            'project_id' => $project->id,
            'name' => 'Phase 2 — TBC',
            'target_days' => '',
            'scope_complete' => '0',
        ])->assertSessionHasNoErrors();

        // No target means we want NULL stored (so effective_target_hours
        // falls through to summing children).
        $this->assertDatabaseHas('milestones', [
            'name' => 'Phase 2 — TBC',
            'target_hours' => null,
        ]);
    }

public function test_user_can_toggle_scope_complete_via_update(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'scope_complete' => false,
        ]);

        $this->actingAs($user)->put("/milestones/{$milestone->id}", [
            'project_id' => $project->id,
            'name' => $milestone->name,
            'target_days' => '',
            'scope_complete' => '1',
        ])->assertRedirect(route('milestones.show', $milestone));

        $milestone->refresh();
        $this->assertTrue((bool) $milestone->scope_complete);
    }

    public function test_user_cannot_create_milestone_under_other_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($intruder)
            ->from('/milestones/create')
            ->post('/milestones', [
                'project_id' => $project->id,
                'name' => 'Sneaky milestone',
                'target_days' => '',
                'scope_complete' => '0',
            ])
            ->assertSessionHasErrors('project_id');

        $this->assertDatabaseMissing('milestones', ['name' => 'Sneaky milestone']);
    }

    public function test_user_cannot_view_other_users_milestone(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);

        $this->actingAs($intruder)
            ->get("/milestones/{$milestone->id}")
            ->assertForbidden();
    }

    public function test_index_lists_only_users_own_milestones(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceProject = Project::factory()->create(['owner_id' => $alice->id]);
        $bobProject = Project::factory()->create(['owner_id' => $bob->id]);

        $mine = Milestone::factory()->create([
            'project_id' => $aliceProject->id,
            'name' => 'My milestone',
        ]);
        $theirs = Milestone::factory()->create([
            'project_id' => $bobProject->id,
            'name' => 'Their milestone',
        ]);

        $this->actingAs($alice)
            ->get('/milestones')
            ->assertOk()
            ->assertSee('My milestone')
            ->assertDontSee('Their milestone');
    }

    public function test_deleting_milestone_nulls_deliverables_milestone_link_but_keeps_deliverable(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);

        $this->actingAs($user)
            ->delete("/milestones/{$milestone->id}")
            ->assertRedirect(route('milestones.index'));

        $this->assertDatabaseMissing('milestones', ['id' => $milestone->id]);
        $this->assertDatabaseHas('deliverables', [
            'id' => $deliverable->id,
            'milestone_id' => null,
        ]);
    }

    public function test_deliverable_form_rejects_milestone_from_a_different_project(): void
    {
        $user = User::factory()->create();
        $clientA = Client::factory()->create(['owner_id' => $user->id]);
        $clientB = Client::factory()->create(['owner_id' => $user->id]);
        $projectA = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $clientA->id,
        ]);
        $projectB = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $clientB->id,
        ]);
        $milestoneOfB = Milestone::factory()->create(['project_id' => $projectB->id]);

        $this->actingAs($user)
            ->from('/deliverables/create')
            ->post('/deliverables', [
                'project_id' => $projectA->id,
                'milestone_id' => $milestoneOfB->id,
                'name' => 'Cross-project mismatch',
                'target_days' => 1.0,
                'status' => 'R',
            ])
            ->assertSessionHasErrors('milestone_id');

        $this->assertDatabaseMissing('deliverables', ['name' => 'Cross-project mismatch']);
    }

    public function test_deliverable_can_be_created_with_a_same_project_milestone(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)->post('/deliverables', [
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'name' => 'Wireframes',
            'target_days' => 1.0,
            'status' => 'R',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('deliverables', [
            'name' => 'Wireframes',
            'milestone_id' => $milestone->id,
        ]);
    }

    public function test_deliverable_milestone_can_be_cleared_via_update(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);

        // Form submits empty string for "— No milestone —"; should normalise to null.
        $this->actingAs($user)->put("/deliverables/{$deliverable->id}", [
            'project_id' => $project->id,
            'milestone_id' => '',
            'name' => $deliverable->name,
            'target_days' => 1.0,
            'status' => 'R',
        ])->assertSessionHasNoErrors();

        $deliverable->refresh();
        $this->assertNull($deliverable->milestone_id);
    }

    public function test_milestone_create_prefills_project_from_query_param(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id, 'name' => 'TargetProject']);

        $this->actingAs($user)
            ->get("/milestones/create?project={$project->id}")
            ->assertOk()
            ->assertSee('TargetProject');
    }

    public function test_milestones_appear_in_nav_for_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Milestones');
    }
}
