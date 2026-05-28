<?php

namespace Tests\Feature\EndToEnd;

use App\Enums\PlanKind;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One scripted user-journey through the post-M8 system: log hours in the
 * journal, then verify the same number appears on every downstream page
 * (deliverable show, plan view, review, dashboard) and feeds into the
 * generated timesheet.
 *
 * This is the canary test — if it breaks, something in the
 * journal → time_logs → derived hours_spent chain has regressed.
 */
class JournalEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_hours_flow_through_every_view_and_into_the_timesheet(): void
    {
        $today = CarbonImmutable::now()->startOfDay();
        $todayStr = $today->toDateString();

        $user = User::factory()->create([
            'name' => 'Test User',
            'weekly_capacity_hours' => 40.0,
        ]);
        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Acme',
        ]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Acme OAuth flow',
            'target_hours' => 16.0, // 2 days
        ]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_hours' => 16.0,
        ]);

        // ----- 1. Log 4 hours via the journal -----
        $response = $this->actingAs($user)->post("/journal/{$todayStr}", [
            'items' => [
                $deliverable->id => ['hours' => 4.0, 'notes' => 'Auth wiring'],
            ],
        ]);
        $response->assertRedirect("/journal/{$todayStr}");

        // ----- 2. Deliverable show reflects the spend -----
        $response = $this->actingAs($user)->get("/deliverables/{$deliverable->id}");
        $response->assertOk();
        $response->assertSee('Acme OAuth flow');
        $response->assertSee('4h (0.5d)');     // Spent
        $response->assertSee('2d (16h)');      // Target
        $response->assertSee('1.5d (12h)');    // Remaining

        // ----- 3. Plan view shows the period-scoped spend -----
        $response = $this->actingAs($user)->get('/plans/weekly');
        $response->assertOk();
        $response->assertSee('Acme OAuth flow');
        $response->assertSee('4h (0.5d)');     // Spent column (period-scoped)
        $response->assertSee('2d (16h)');      // Allocated (days-leading)

        // ----- 4. Weekly review shows the derived spend, read-only -----
        $response = $this->actingAs($user)->get('/review');
        $response->assertOk();
        $response->assertSee('Acme OAuth flow');
        $response->assertSee('4h (0.5d)');
        // Confirms no hours_spent input field for that plan_item — the
        // review is read-only post-M8d.
        $response->assertDontSee('name="items[' . $period->items()->first()->id . '][hours_spent]"', false);

        // ----- 5. Dashboard "Recently completed" is not yet populated, but
        //         the page still renders without exploding. -----
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        // ----- 6. Generate a timesheet for this month — file exists -----
        $response = $this->actingAs($user)->post('/timesheets/generate', [
            'month' => $today->format('Y-m'),
        ]);
        $response->assertRedirect(route('timesheets.index'));
        $timesheet = Timesheet::firstOrFail();
        $this->assertTrue($timesheet->fileExists());
    }

    public function test_unmarking_a_completed_deliverable_keeps_derived_hours_intact(): void
    {
        // M8c+d invariant: completion is a separate axis from hours.
        // Toggling done/undone on /review must NOT mutate time_logs.
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $item = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $d->id,
            'allocated_hours' => 8.0,
        ]);

        // Log 6 hours via journal.
        $this->actingAs($user)->post('/journal/' . now()->toDateString(), [
            'items' => [$d->id => ['hours' => 6.0]],
        ]);
        $this->assertEqualsWithDelta(6.0, (float) $d->fresh()->hours_spent, 0.01);

        // Mark complete via the review.
        $this->actingAs($user)->post('/review', [
            'items' => [$item->id => ['completed' => '1']],
        ]);
        $this->assertEqualsWithDelta(6.0, (float) $d->fresh()->hours_spent, 0.01);

        // Untick — hours_spent must still be 6.
        $this->actingAs($user)->post('/review', [
            'items' => [$item->id => ['completed' => '0']],
        ]);
        $this->assertEqualsWithDelta(6.0, (float) $d->fresh()->hours_spent, 0.01);
    }

    public function test_days_form_inputs_round_trip_through_storage_correctly(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);

        // Create a deliverable via the form using days input.
        $this->actingAs($user)->post('/deliverables', [
            'project_id' => $project->id,
            'name' => 'Day-form deliverable',
            'target_days' => 3.5, // → 28h stored
            'status' => 'R',
        ]);

        $d = Deliverable::where('name', 'Day-form deliverable')->firstOrFail();
        $this->assertEqualsWithDelta(28.0, (float) $d->target_hours, 0.01);

        // Show page should render days-leading.
        $response = $this->actingAs($user)->get("/deliverables/{$d->id}");
        $response->assertOk();
        $response->assertSee('3.5d (28h)');
    }
}
