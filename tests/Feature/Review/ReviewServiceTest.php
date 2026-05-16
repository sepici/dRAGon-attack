<?php

namespace Tests\Feature\Review;

use App\Enums\PlanKind;
use App\Enums\Status;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\WeeklyReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Service tests for the weekly review.
 *
 * Since M8c/d the review is a retrospective only — it toggles
 * completed_at/status/notes but does NOT touch hours. Hours come from
 * the daily journal (time_logs).
 */
class ReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private WeeklyReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WeeklyReviewService();
    }

    /** @return array{0:User,1:Deliverable,2:PlanPeriod,3:PlanItem} */
    private function setupChain(array $deliverableOverrides = [], array $itemOverrides = []): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $project = Project::factory()->create([
            'owner_id' => $user->id, 'client_id' => $client->id,
        ]);
        $deliverable = Deliverable::factory()->create(array_merge([
            'project_id' => $project->id,
        ], $deliverableOverrides));
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $item = PlanItem::factory()->create(array_merge([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_hours' => 16.0,
            'status' => Status::Red,
        ], $itemOverrides));

        return [$user, $deliverable, $period, $item];
    }

    // ---------- Mark complete -------------------------------------------------

    public function test_marking_item_complete_sets_status_green_and_completed_at(): void
    {
        [, , $period, $item] = $this->setupChain();

        $this->service->process($period, [
            $item->id => ['completed' => true, 'notes' => 'shipped'],
        ]);

        $item->refresh();
        $this->assertSame(Status::Green, $item->status);
        $this->assertNotNull($item->completed_at);
        $this->assertSame('shipped', $item->notes);
    }

    public function test_unmarking_a_completed_item_clears_completed_at(): void
    {
        [, , $period, $item] = $this->setupChain([], [
            'completed_at' => now(),
            'status' => Status::Green,
        ]);

        $this->service->process($period, [
            $item->id => ['completed' => false],
        ]);

        $item->refresh();
        $this->assertNull($item->completed_at);
    }

    // ---------- Recolour rules ----------------------------------------------

    public function test_incomplete_item_with_no_hours_keeps_existing_status(): void
    {
        [, , $period, $item] = $this->setupChain(['deadline' => null], ['status' => Status::Amber]);

        $this->service->process($period, [
            $item->id => ['completed' => false],
        ]);

        $this->assertSame(Status::Amber, $item->fresh()->status);
    }

    public function test_incomplete_item_with_logged_hours_recolours_amber(): void
    {
        [$user, $deliverable, $period, $item] = $this->setupChain(
            ['deadline' => null],
            ['status' => Status::Red],
        );
        // Time logged in the journal during this period — drives recolour.
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => $period->starts_on,
            'hours' => 4.0,
        ]);

        $this->service->process($period, [
            $item->id => ['completed' => false],
        ]);

        $this->assertSame(Status::Amber, $item->fresh()->status);
    }

    public function test_incomplete_item_with_past_deadline_recolours_red(): void
    {
        $pastDate = CarbonImmutable::now()->subDays(5);
        [$user, $deliverable, $period, $item] = $this->setupChain(
            ['deadline' => $pastDate],
            ['status' => Status::Amber],
        );
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => $period->starts_on,
            'hours' => 4.0,
        ]);

        $this->service->process($period, [
            $item->id => ['completed' => false],
        ]);

        // Red beats amber when deadline is in the past.
        $this->assertSame(Status::Red, $item->fresh()->status);
    }

    // ---------- Roll forward ------------------------------------------------

    public function test_roll_forward_copies_incomplete_items_to_next_week(): void
    {
        [, , $period, $item] = $this->setupChain();

        // One incomplete (the seeded item), and one already-complete.
        $deliverable2 = Deliverable::factory()->create([
            'project_id' => $item->deliverable->project_id,
        ]);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable2->id,
            'allocated_hours' => 8.0,
            'completed_at' => now(),
            'status' => Status::Green,
        ]);

        $nextPeriod = $this->service->rollForward($period);

        $this->assertSame(
            CarbonImmutable::parse($period->starts_on)->addWeek()->toDateString(),
            $nextPeriod->starts_on->toDateString(),
        );

        // Only the incomplete deliverable should have been copied.
        $copied = $nextPeriod->items()->get();
        $this->assertCount(1, $copied);
        $this->assertSame($item->deliverable_id, $copied->first()->deliverable_id);
        $this->assertEqualsWithDelta(
            (float) $item->allocated_hours,
            (float) $copied->first()->allocated_hours,
            0.01,
        );
        $this->assertSame(Status::Red, $copied->first()->status);
    }

    public function test_roll_forward_is_idempotent(): void
    {
        [, , $period] = $this->setupChain();

        $a = $this->service->rollForward($period);
        $b = $this->service->rollForward($period);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $a->items()->count());  // not duplicated
    }

    public function test_roll_forward_rejects_non_weekly_periods(): void
    {
        $user = User::factory()->create();
        $monthly = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Monthly);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->rollForward($monthly);
    }

    // ---------- Derived hours_spent on plan_items --------------------------

    public function test_plan_item_hours_spent_is_derived_from_time_logs_in_period_window(): void
    {
        [$user, $deliverable, $period, $item] = $this->setupChain();

        // Inside the period: counted.
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => $period->starts_on,
            'hours' => 2.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => $period->ends_on,
            'hours' => 3.0,
        ]);
        // Outside the period (before): NOT counted.
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => $period->starts_on->copy()->subDay(),
            'hours' => 99.0,
        ]);
        // Outside the period (after): NOT counted.
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => $period->ends_on->copy()->addDay(),
            'hours' => 99.0,
        ]);

        $this->assertEqualsWithDelta(5.0, (float) $item->fresh()->hours_spent, 0.01);
    }

    public function test_deliverable_hours_spent_sums_all_time_logs(): void
    {
        [$user, $deliverable] = $this->setupChain();
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'log_date' => '2026-05-10', 'hours' => 2.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'log_date' => '2026-05-15', 'hours' => 3.5,
        ]);

        $this->assertEqualsWithDelta(5.5, (float) $deliverable->fresh()->hours_spent, 0.01);
    }

    public function test_with_hours_spent_scope_eager_loads_the_sum(): void
    {
        [$user, $deliverable] = $this->setupChain();
        TimeLog::factory()->count(2)->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'hours' => 1.5,
        ]);

        $hydrated = Deliverable::withHoursSpent()->find($deliverable->id);

        // After withSum, hours_spent is on $hydrated->attributes — the
        // accessor short-circuits and doesn't issue another query.
        $this->assertArrayHasKey('hours_spent', $hydrated->getAttributes());
        $this->assertEqualsWithDelta(3.0, (float) $hydrated->hours_spent, 0.01);
    }

    public function test_period_load_hours_spent_hydrates_all_items_in_one_pass(): void
    {
        [$user, $d1, $period] = $this->setupChain();
        $d2 = Deliverable::factory()->create(['project_id' => $d1->project_id]);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $d2->id,
            'allocated_hours' => 4.0,
        ]);

        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d1->id,
            'log_date' => $period->starts_on, 'hours' => 1.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $d2->id,
            'log_date' => $period->starts_on, 'hours' => 2.5,
        ]);

        $period->load('items');
        $period->loadHoursSpent();

        $byDeliverable = $period->items->keyBy('deliverable_id');
        $this->assertEqualsWithDelta(1.0, (float) $byDeliverable[$d1->id]->hours_spent, 0.01);
        $this->assertEqualsWithDelta(2.5, (float) $byDeliverable[$d2->id]->hours_spent, 0.01);
    }
}
