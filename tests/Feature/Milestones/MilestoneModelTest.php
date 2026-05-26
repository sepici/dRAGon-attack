<?php

namespace Tests\Feature\Milestones;

use App\Enums\PlanKind;
use App\Enums\Status;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the wiring + invariants for M12a. Covers:
 *   - schema (milestones table, FKs)
 *   - relationships (project ↔ milestones ↔ deliverables, plan_items)
 *   - derived status (scope_complete gate + RAGB rollup)
 *   - effective_target_hours (manual vs sum-of-children)
 *   - hours_spent rollup from time_logs
 *   - exactly-one-of-deliverable-or-milestone guard on plan_items
 *   - cascade behaviours
 */
class MilestoneModelTest extends TestCase
{
    use RefreshDatabase;

    private function setupProjectWithMilestone(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $milestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'name' => 'Phase 1',
        ]);
        return [$user, $project, $milestone];
    }

    // ---------- Wiring ------------------------------------------------------

    public function test_project_has_many_milestones(): void
    {
        $project = Project::factory()->create();
        Milestone::factory()->count(3)->create(['project_id' => $project->id]);

        $this->assertSame(3, $project->milestones()->count());
    }

    public function test_milestone_belongs_to_project(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        $this->assertSame($project->id, $milestone->project->id);
    }

    public function test_deliverable_can_be_assigned_to_a_milestone(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        $d = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);

        $this->assertSame($milestone->id, $d->fresh()->milestone->id);
        $this->assertSame(1, $milestone->deliverables()->count());
    }

    public function test_deliverable_can_exist_without_a_milestone(): void
    {
        $project = Project::factory()->create();
        $d = Deliverable::factory()->create(['project_id' => $project->id]);
        $this->assertNull($d->milestone_id);
        $this->assertNull($d->milestone);
    }

    // ---------- Derived status ---------------------------------------------

    public function test_derived_status_red_when_any_child_red(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'status' => Status::Red,
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'status' => Status::Green,
        ]);

        $this->assertSame(Status::Red, $milestone->fresh()->status);
    }

    public function test_derived_status_blocked_when_any_child_blocked(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'status' => Status::Blocked,
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'status' => Status::Green,
        ]);

        $this->assertSame(Status::Blocked, $milestone->fresh()->status);
    }

    public function test_derived_status_amber_when_any_child_amber(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'status' => Status::Amber,
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'status' => Status::Green,
        ]);

        $this->assertSame(Status::Amber, $milestone->fresh()->status);
    }

    public function test_all_green_but_not_scope_complete_stays_amber(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'status' => Status::Green,
        ]);

        $milestone->refresh();
        $this->assertSame(Status::Amber, $milestone->status, 'scope unconfirmed → still amber');
        $this->assertTrue($milestone->isScopeAmbiguous(), 'flag should fire');
    }

    public function test_all_green_with_scope_complete_is_green(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        $milestone->update(['scope_complete' => true]);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'status' => Status::Green,
        ]);

        $milestone->refresh();
        $this->assertSame(Status::Green, $milestone->status);
        $this->assertFalse($milestone->isScopeAmbiguous());
    }

    public function test_empty_milestone_is_red(): void
    {
        [, , $milestone] = $this->setupProjectWithMilestone();
        $this->assertSame(Status::Red, $milestone->status);
        $this->assertFalse($milestone->isScopeAmbiguous());
    }

    // ---------- Targets and hours ------------------------------------------

    public function test_effective_target_hours_uses_manual_value_when_set(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        $milestone->update(['target_hours' => 40.0]);
        // Child target_hours should be ignored when milestone has its own target.
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'target_hours' => 100.0,
        ]);

        $this->assertEqualsWithDelta(40.0, $milestone->fresh()->effective_target_hours, 0.01);
    }

    public function test_effective_target_hours_sums_children_when_manual_null(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'target_hours' => 24.0,
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
            'target_hours' => 16.0,
        ]);

        $this->assertEqualsWithDelta(40.0, $milestone->fresh()->effective_target_hours, 0.01);
    }

    public function test_hours_spent_rolls_up_from_child_time_logs(): void
    {
        [$user, $project, $milestone] = $this->setupProjectWithMilestone();
        $d1 = Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
        ]);
        $d2 = Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
        ]);
        // d3 is in the same project but NOT in this milestone — should NOT count.
        $d3 = Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => null,
        ]);

        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d1->id, 'hours' => 3.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d2->id, 'hours' => 1.5,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d3->id, 'hours' => 99.0,
        ]);

        $this->assertEqualsWithDelta(4.5, $milestone->fresh()->hours_spent, 0.01);
        $this->assertEqualsWithDelta(0.5625, $milestone->fresh()->days_spent, 0.0001);
    }

    // ---------- Plan items: exactly-one-of guard --------------------------

    public function test_plan_item_with_deliverable_only_is_allowed(): void
    {
        [$user, $project] = $this->setupProjectWithMilestone();
        $d = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        $item = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $d->id,
        ]);

        $this->assertTrue($item->isDeliverableAllocation());
        $this->assertFalse($item->isMilestoneAllocation());
    }

    public function test_plan_item_with_milestone_only_is_allowed(): void
    {
        [$user, $project, $milestone] = $this->setupProjectWithMilestone();
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        $item = PlanItem::factory()->forMilestone($milestone)->create([
            'plan_period_id' => $period->id,
            'allocated_hours' => 40.0,
        ]);

        $this->assertFalse($item->isDeliverableAllocation());
        $this->assertTrue($item->isMilestoneAllocation());
    }

    public function test_plan_item_with_both_throws(): void
    {
        [$user, $project, $milestone] = $this->setupProjectWithMilestone();
        $d = Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
        ]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        $this->expectException(\LogicException::class);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $d->id,
            'milestone_id' => $milestone->id,
        ]);
    }

    public function test_plan_item_with_neither_throws(): void
    {
        $user = User::factory()->create();
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        $this->expectException(\LogicException::class);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => null,
            'milestone_id' => null,
        ]);
    }

    // ---------- Plan-item period-scoped spent -----------------------------

    public function test_milestone_plan_item_spent_sums_all_child_deliverable_logs_in_period(): void
    {
        [$user, $project, $milestone] = $this->setupProjectWithMilestone();
        $d1 = Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
        ]);
        $d2 = Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
        ]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $item = PlanItem::factory()->forMilestone($milestone)->create([
            'plan_period_id' => $period->id,
            'allocated_hours' => 40.0,
        ]);

        // Inside the period — both should count.
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d1->id,
            'log_date' => $period->starts_on, 'hours' => 2.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d2->id,
            'log_date' => $period->ends_on, 'hours' => 3.5,
        ]);
        // Outside the period — should NOT count.
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d1->id,
            'log_date' => $period->starts_on->copy()->subDay(),
            'hours' => 99.0,
        ]);

        $this->assertEqualsWithDelta(5.5, $item->fresh()->hours_spent, 0.01);
    }

    // ---------- Cascade behaviour -----------------------------------------

    public function test_deleting_project_cascades_to_milestones(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        $this->assertSame(1, Milestone::count());

        $project->delete();
        $this->assertSame(0, Milestone::count());
    }

    public function test_deleting_milestone_nulls_deliverable_link_but_keeps_deliverable(): void
    {
        [$_, $project, $milestone] = $this->setupProjectWithMilestone();
        $d = Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $milestone->id,
        ]);

        $milestone->delete();

        $d->refresh();
        $this->assertNotNull($d);
        $this->assertNull($d->milestone_id);
    }

    public function test_deleting_milestone_cascades_to_milestone_plan_items(): void
    {
        [$user, $project, $milestone] = $this->setupProjectWithMilestone();
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $item = PlanItem::factory()->forMilestone($milestone)->create([
            'plan_period_id' => $period->id,
        ]);

        $milestone->delete();
        $this->assertNull($item->fresh());
    }
}
