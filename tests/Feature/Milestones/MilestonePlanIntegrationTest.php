<?php

namespace Tests\Feature\Milestones;

use App\Enums\PlanKind;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M12c — milestones in the planning + review + dashboard surfaces.
 *
 * The schema and exactly-one model invariant were added in M12a; here we
 * cover the controller/view/service plumbing that lets a user actually
 * allocate days to a milestone, see grouped rows, roll forward, and read
 * the dashboard rollup.
 */
class MilestonePlanIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function ownedChain(): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $client->id,
        ]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        return [$user, $project, $milestone, $period];
    }

    // ---------- StorePlanItemRequest: milestone path ----------------------

    public function test_user_can_allocate_days_to_a_milestone(): void
    {
        [$user, $project, $milestone, $period] = $this->ownedChain();

        $this->actingAs($user)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $period->id,
                'milestone_id' => $milestone->id,
                'allocated_days' => 3.0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = PlanItem::where('plan_period_id', $period->id)->firstOrFail();
        $this->assertNull($item->deliverable_id);
        $this->assertSame($milestone->id, $item->milestone_id);
        $this->assertEqualsWithDelta(24.0, (float) $item->allocated_hours, 0.01);
    }

    public function test_cannot_allocate_to_both_deliverable_and_milestone(): void
    {
        [$user, $project, $milestone, $period] = $this->ownedChain();
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $period->id,
                'deliverable_id' => $deliverable->id,
                'milestone_id' => $milestone->id,
                'allocated_days' => 1.0,
            ])
            ->assertSessionHasErrors('deliverable_id');

        $this->assertSame(0, PlanItem::count());
    }

    public function test_must_pick_either_deliverable_or_milestone(): void
    {
        [$user, , , $period] = $this->ownedChain();

        $this->actingAs($user)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $period->id,
                'allocated_days' => 1.0,
            ])
            ->assertSessionHasErrors('deliverable_id');

        $this->assertSame(0, PlanItem::count());
    }

    public function test_cannot_allocate_to_someone_elses_milestone(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $projectOfB = Project::factory()->create(['owner_id' => $userB->id]);
        $milestoneOfB = Milestone::factory()->create(['project_id' => $projectOfB->id]);
        $periodOfA = PlanPeriod::findOrCreateCurrentFor($userA, PlanKind::Weekly);

        $this->actingAs($userA)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $periodOfA->id,
                'milestone_id' => $milestoneOfB->id,
                'allocated_days' => 1.0,
            ])
            ->assertSessionHasErrors('milestone_id');
    }

    public function test_cannot_add_same_milestone_twice_to_one_period(): void
    {
        [$user, $project, $milestone, $period] = $this->ownedChain();
        PlanItem::factory()->forMilestone($milestone)->create([
            'plan_period_id' => $period->id,
            'allocated_hours' => 16.0,
        ]);

        $this->actingAs($user)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $period->id,
                'milestone_id' => $milestone->id,
                'allocated_days' => 2.0,
            ])
            ->assertSessionHasErrors('milestone_id');

        $this->assertSame(1, PlanItem::where('plan_period_id', $period->id)->count());
    }

    // ---------- loadHoursSpent: milestone rollup --------------------------

    public function test_load_hours_spent_sums_child_deliverable_logs_for_milestone_items(): void
    {
        [$user, $project, $milestone, $period] = $this->ownedChain();
        $d1 = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);
        $d2 = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);

        // Log 4h on d1 and 2.5h on d2 inside the period window. Plus 5h on
        // d1 OUTSIDE the period window (must not be counted).
        $inside = CarbonImmutable::parse($period->starts_on);
        $outside = CarbonImmutable::parse($period->ends_on)->addDays(2);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d1->id,
            'hours' => 4.0, 'log_date' => $inside,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d2->id,
            'hours' => 2.5, 'log_date' => $inside,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d1->id,
            'hours' => 5.0, 'log_date' => $outside,
        ]);

        $item = PlanItem::factory()->forMilestone($milestone)->create([
            'plan_period_id' => $period->id,
            'allocated_hours' => 24.0,
        ]);

        $period->load('items');
        $period->loadHoursSpent();

        $this->assertEqualsWithDelta(6.5, (float) $period->items->find($item->id)->hours_spent, 0.01);
    }

    // ---------- Plan view grouping ----------------------------------------

    public function test_plan_view_groups_items_under_milestone_headers(): void
    {
        [$user, $project, $milestone, $period] = $this->ownedChain();
        $milestone->update(['name' => 'Phase 1 — Discovery']);
        $deliverableInMilestone = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'name' => 'Wireframes',
        ]);
        $orphanDeliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => null,
            'name' => 'One-off chore',
        ]);

        PlanItem::factory()->forMilestone($milestone)->create([
            'plan_period_id' => $period->id,
            'allocated_hours' => 24.0,
        ]);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverableInMilestone->id,
            'allocated_hours' => 8.0,
        ]);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $orphanDeliverable->id,
            'allocated_hours' => 4.0,
        ]);

        $this->actingAs($user)
            ->get('/plans/weekly')
            ->assertOk()
            ->assertSee('Phase 1 — Discovery')
            ->assertSee('Milestone envelope')
            ->assertSee('Wireframes')
            ->assertSee('(no milestone)')
            ->assertSee('One-off chore');
    }

    public function test_plan_form_offers_both_deliverables_and_milestones(): void
    {
        [$user, $project, $milestone] = $this->ownedChain();
        $milestone->update(['name' => 'Available milestone XYZ']);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'name' => 'Available deliverable XYZ',
        ]);

        $this->actingAs($user)
            ->get('/plans/weekly')
            ->assertOk()
            ->assertSee('Available milestone XYZ')
            ->assertSee('Available deliverable XYZ');
    }

    // ---------- Roll-forward includes milestone items ---------------------

    public function test_roll_forward_copies_milestone_envelopes_into_next_week(): void
    {
        [$user, $project, $milestone, $thisWeek] = $this->ownedChain();
        PlanItem::factory()->forMilestone($milestone)->create([
            'plan_period_id' => $thisWeek->id,
            'allocated_hours' => 16.0,
            'completed_at' => null,
        ]);

        $this->actingAs($user)
            ->from('/review')
            ->post('/review/roll-forward')
            ->assertRedirect();

        $nextStart = CarbonImmutable::parse($thisWeek->starts_on)->addWeek();
        $nextWeek = PlanPeriod::where('owner_id', $user->id)
            ->where('kind', PlanKind::Weekly->value)
            ->whereDate('starts_on', $nextStart->toDateString())
            ->firstOrFail();

        $this->assertSame(
            1,
            $nextWeek->items()->where('milestone_id', $milestone->id)->count(),
            'Milestone envelope should roll forward.',
        );
    }

    // ---------- Dashboard milestone card ----------------------------------

    public function test_dashboard_shows_milestone_status_counts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);

        // One milestone with all-Red children → derived Red
        $red = Milestone::factory()->create(['project_id' => $project->id]);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $red->id,
            'status' => \App\Enums\Status::Red,
        ]);
        // One milestone with all-Green children, scope confirmed → Green
        $green = Milestone::factory()->create([
            'project_id' => $project->id,
            'scope_complete' => true,
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $green->id,
            'status' => \App\Enums\Status::Green,
        ]);
        // One all-Green-but-scope-unconfirmed → derived Amber + ambiguous flag
        $ambig = Milestone::factory()->create([
            'project_id' => $project->id,
            'scope_complete' => false,
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'milestone_id' => $ambig->id,
            'status' => \App\Enums\Status::Green,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Milestones by status')
            ->assertSee('scope not confirmed');
    }

    public function test_dashboard_empty_state_when_no_milestones(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Milestones by status')
            ->assertSee('group deliverables into phases');
    }

    // ---------- Review page renders milestone groups ----------------------

    public function test_review_page_groups_items_under_milestones(): void
    {
        [$user, $project, $milestone, $period] = $this->ownedChain();
        $milestone->update(['name' => 'Backend holding']);
        $d = Deliverable::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'name' => 'OAuth wiring',
        ]);
        PlanItem::factory()->forMilestone($milestone)->create([
            'plan_period_id' => $period->id,
            'allocated_hours' => 16.0,
        ]);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $d->id,
            'allocated_hours' => 8.0,
        ]);

        $this->actingAs($user)
            ->get('/review')
            ->assertOk()
            ->assertSee('Backend holding')
            ->assertSee('Milestone envelope')
            ->assertSee('OAuth wiring');
    }
}
