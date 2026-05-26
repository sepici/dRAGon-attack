<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Moscow;
use App\Enums\PlanKind;
use App\Enums\Status;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\ApiAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M12d — REST surface for milestones, plus the milestone_id wiring on
 * deliverable + plan-item write endpoints.
 *
 * Mirrors WriteApiTest's style: Sanctum acting-as with the exact ability
 * the route requires, JSON in/out, no web session.
 */
class MilestoneApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUserWithAbilities(array $abilities = ['*']): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, $abilities);
        return $user;
    }

    // ---------- GET /milestones ------------------------------------------

    public function test_index_lists_owned_milestones(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $mine = Project::factory()->create(['owner_id' => $user->id]);
        $other = Project::factory()->create();
        Milestone::factory()->create(['project_id' => $mine->id, 'name' => 'Mine']);
        Milestone::factory()->create(['project_id' => $other->id, 'name' => 'Theirs']);

        $response = $this->getJson('/api/v1/milestones');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine');
    }

    public function test_index_filters_by_project_id(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $projectA = Project::factory()->create(['owner_id' => $user->id]);
        $projectB = Project::factory()->create(['owner_id' => $user->id]);
        Milestone::factory()->create(['project_id' => $projectA->id, 'name' => 'A-1']);
        Milestone::factory()->create(['project_id' => $projectA->id, 'name' => 'A-2']);
        Milestone::factory()->create(['project_id' => $projectB->id, 'name' => 'B-1']);

        $this->getJson("/api/v1/milestones?project_id={$projectA->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_scope_complete(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        Milestone::factory()->create(['project_id' => $project->id, 'scope_complete' => true, 'name' => 'Done scope']);
        Milestone::factory()->create(['project_id' => $project->id, 'scope_complete' => false, 'name' => 'Open scope']);

        $this->getJson('/api/v1/milestones?scope_complete=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Done scope');
    }

    // ---------- GET /milestones/{id} -------------------------------------

    public function test_show_returns_derived_fields(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'target_hours' => null,
            'scope_complete' => false,
        ]);
        // Two child deliverables, one Green one Red, totalling 24h target.
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'target_hours' => 16.0,
            'status' => Status::Red,
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'target_hours' => 8.0,
            'status' => Status::Green,
        ]);

        $response = $this->getJson("/api/v1/milestones/{$milestone->id}");
        $response->assertOk()
            ->assertJsonPath('data.id', $milestone->id)
            ->assertJsonPath('data.target_hours', null)
            // 24.0 round-trips through JSON as 24 (no decimal); assertJsonPath
            // does strict equality so compare to the integer form.
            ->assertJsonPath('data.effective_target_hours', 24)
            ->assertJsonPath('data.status', 'R')
            ->assertJsonPath('data.scope_complete', false)
            ->assertJsonPath('data.scope_ambiguous', false);
    }

    public function test_show_404_for_other_users_milestone(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $other = Milestone::factory()->create();

        $this->getJson("/api/v1/milestones/{$other->id}")->assertNotFound();
    }

    // ---------- POST /milestones -----------------------------------------

    public function test_create_milestone_minimum_fields(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);

        $response = $this->postJson('/api/v1/milestones', [
            'project_id' => $project->id,
            'name' => 'Phase 1 — Discovery',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Phase 1 — Discovery')
            ->assertJsonPath('data.target_hours', null)
            ->assertJsonPath('data.scope_complete', false);

        $this->assertDatabaseHas('milestones', [
            'name' => 'Phase 1 — Discovery',
            'target_hours' => null,
            'scope_complete' => false,
        ]);
    }

    public function test_create_milestone_with_target_hours_and_moscow(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);

        $response = $this->postJson('/api/v1/milestones', [
            'project_id' => $project->id,
            'name' => 'Backend holding',
            'target_hours' => 40.0,
            'moscow' => 'M',
            'deadline' => '2026-06-30',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.target_hours', 40)
            ->assertJsonPath('data.moscow', 'M')
            ->assertJsonPath('data.deadline', '2026-06-30');
    }

    public function test_create_milestone_rejects_someone_elses_project(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $other = Project::factory()->create();

        $this->postJson('/api/v1/milestones', [
            'project_id' => $other->id,
            'name' => 'Sneaky',
        ])->assertStatus(422)
          ->assertJsonValidationErrors('project_id');
    }

    public function test_create_milestone_requires_write_ability(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);

        $this->postJson('/api/v1/milestones', [
            'project_id' => $project->id,
            'name' => 'Read-only attempt',
        ])->assertForbidden();
    }

    // ---------- PUT /milestones/{id} -------------------------------------

    public function test_update_milestone_can_toggle_scope_complete(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'scope_complete' => false,
        ]);

        $this->putJson("/api/v1/milestones/{$milestone->id}", [
            'scope_complete' => true,
        ])->assertOk()
          ->assertJsonPath('data.scope_complete', true);

        $this->assertTrue((bool) $milestone->fresh()->scope_complete);
    }

    public function test_update_milestone_can_clear_target_hours(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'target_hours' => 40.0,
        ]);

        $this->putJson("/api/v1/milestones/{$milestone->id}", [
            'target_hours' => null,
        ])->assertOk()
          ->assertJsonPath('data.target_hours', null);

        $this->assertDatabaseHas('milestones', [
            'id' => $milestone->id,
            'target_hours' => null,
        ]);
    }

    public function test_update_milestone_404_for_other_user(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $other = Milestone::factory()->create();

        $this->putJson("/api/v1/milestones/{$other->id}", ['name' => 'Hijack'])
            ->assertNotFound();
    }

    // ---------- DELETE /milestones/{id} ----------------------------------

    public function test_delete_milestone_nulls_child_deliverable_link(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);

        $this->deleteJson("/api/v1/milestones/{$milestone->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('milestones', ['id' => $milestone->id]);
        $this->assertDatabaseHas('deliverables', [
            'id' => $deliverable->id,
            'milestone_id' => null,
        ]);
    }

    // ---------- Deliverable write surface accepts milestone_id -----------

    public function test_can_create_deliverable_with_milestone_id(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);

        $response = $this->postJson('/api/v1/deliverables', [
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'name' => 'Wireframes',
            'target_hours' => 8.0,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.milestone_id', $milestone->id);
    }

    public function test_deliverable_milestone_must_belong_to_same_project(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $projectA = Project::factory()->create(['owner_id' => $user->id]);
        $projectB = Project::factory()->create(['owner_id' => $user->id]);
        $milestoneOfB = Milestone::factory()->create(['project_id' => $projectB->id]);

        $this->postJson('/api/v1/deliverables', [
            'project_id' => $projectA->id,
            'milestone_id' => $milestoneOfB->id,
            'name' => 'Cross-project mismatch',
            'target_hours' => 4.0,
        ])->assertStatus(422)
          ->assertJsonValidationErrors('milestone_id');
    }

    // ---------- Plan-item write accepts milestone_id ---------------------

    public function test_can_allocate_milestone_envelope_via_api(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);

        $response = $this->postJson('/api/v1/plan-items', [
            'period_kind' => 'weekly',
            'milestone_id' => $milestone->id,
            'allocated_hours' => 24.0,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.milestone_id', $milestone->id)
            ->assertJsonPath('data.deliverable_id', null)
            ->assertJsonPath('data.allocated_hours', 24);
    }

    public function test_plan_item_rejects_both_deliverable_and_milestone(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        $this->postJson('/api/v1/plan-items', [
            'period_kind' => 'weekly',
            'deliverable_id' => $deliverable->id,
            'milestone_id' => $milestone->id,
            'allocated_hours' => 4.0,
        ])->assertStatus(422)
          ->assertJsonValidationErrors('deliverable_id');
    }

    public function test_plan_item_rejects_neither_deliverable_nor_milestone(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);

        $this->postJson('/api/v1/plan-items', [
            'period_kind' => 'weekly',
            'allocated_hours' => 4.0,
        ])->assertStatus(422)
          ->assertJsonValidationErrors('milestone_id');
    }

    public function test_plan_item_rejects_someone_elses_milestone(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $otherMilestone = Milestone::factory()->create();

        $this->postJson('/api/v1/plan-items', [
            'period_kind' => 'weekly',
            'milestone_id' => $otherMilestone->id,
            'allocated_hours' => 4.0,
        ])->assertStatus(422)
          ->assertJsonValidationErrors('milestone_id');
    }

    // ---------- hours_spent rollup on milestone --------------------------

    public function test_milestone_show_includes_hours_spent_rollup(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $d1 = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);
        $d2 = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d1->id, 'hours' => 4.5,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d2->id, 'hours' => 2.5,
        ]);

        $this->getJson("/api/v1/milestones/{$milestone->id}")
            ->assertOk()
            ->assertJsonPath('data.hours_spent', 7);
    }
}
