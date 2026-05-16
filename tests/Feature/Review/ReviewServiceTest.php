<?php

namespace Tests\Feature\Review;

use App\Enums\PlanKind;
use App\Enums\Status;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\User;
use App\Services\WeeklyReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Direct unit-ish tests of WeeklyReviewService. These pin the transactional
 * behaviour (mark complete, recolour, ad-hoc rows, roll-forward) without
 * going through the HTTP layer.
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
            'days_spent' => 0,
        ], $deliverableOverrides));
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $item = PlanItem::factory()->create(array_merge([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_days' => 2.0,
            'days_spent' => 0,
            'status' => Status::Red,
        ], $itemOverrides));

        return [$user, $deliverable, $period, $item];
    }

    // ---------- Mark complete -------------------------------------------------

    public function test_marking_item_complete_sets_status_green_and_completed_at(): void
    {
        [, , $period, $item] = $this->setupChain();

        $this->service->process($period, [
            $item->id => ['days_spent' => 2.0, 'completed' => true, 'notes' => 'shipped'],
        ], []);

        $item->refresh();
        $this->assertSame(Status::Green, $item->status);
        $this->assertNotNull($item->completed_at);
        $this->assertEqualsWithDelta(2.0, (float) $item->days_spent, 0.01);
        $this->assertSame('shipped', $item->notes);
    }

    public function test_marking_complete_increments_deliverable_cumulative_days_spent(): void
    {
        [, $deliverable, $period, $item] = $this->setupChain(['days_spent' => 4.0]);

        $this->service->process($period, [
            $item->id => ['days_spent' => 1.5, 'completed' => true],
        ], []);

        $this->assertEqualsWithDelta(5.5, (float) $deliverable->fresh()->days_spent, 0.01);
    }

    public function test_unmarking_an_already_complete_item_withdraws_days_from_deliverable(): void
    {
        // Set up an already-completed item so the service sees it as "previously complete".
        [, $deliverable, $period, $item] = $this->setupChain(
            ['days_spent' => 2.0],
            ['days_spent' => 2.0, 'completed_at' => now(), 'status' => Status::Green],
        );

        $this->service->process($period, [
            $item->id => ['days_spent' => 2.0, 'completed' => false],
        ], []);

        $item->refresh();
        $this->assertNull($item->completed_at);
        // Deliverable cumulative goes from 2.0 → 0.0 because we un-completed.
        $this->assertEqualsWithDelta(0.0, (float) $deliverable->fresh()->days_spent, 0.01);
    }

    public function test_repeated_save_of_already_complete_item_does_not_double_count(): void
    {
        [, $deliverable, $period, $item] = $this->setupChain();

        // First review: complete it.
        $this->service->process($period, [
            $item->id => ['days_spent' => 1.0, 'completed' => true],
        ], []);
        $this->assertEqualsWithDelta(1.0, (float) $deliverable->fresh()->days_spent, 0.01);

        // Second save with same data — should NOT add another 1.0 to the deliverable.
        $this->service->process($period, [
            $item->id => ['days_spent' => 1.0, 'completed' => true],
        ], []);
        $this->assertEqualsWithDelta(1.0, (float) $deliverable->fresh()->days_spent, 0.01);
    }

    // ---------- Recolour rules ----------------------------------------------

    public function test_incomplete_item_with_no_spent_keeps_existing_status(): void
    {
        [, , $period, $item] = $this->setupChain(['deadline' => null], ['status' => Status::Amber]);

        $this->service->process($period, [
            $item->id => ['days_spent' => 0, 'completed' => false],
        ], []);

        $this->assertSame(Status::Amber, $item->fresh()->status);
    }

    public function test_incomplete_item_with_days_spent_recolours_amber(): void
    {
        [, , $period, $item] = $this->setupChain(['deadline' => null], ['status' => Status::Red]);

        $this->service->process($period, [
            $item->id => ['days_spent' => 0.5, 'completed' => false],
        ], []);

        $this->assertSame(Status::Amber, $item->fresh()->status);
    }

    public function test_incomplete_item_with_past_deadline_recolours_red(): void
    {
        $pastDate = CarbonImmutable::now()->subDays(5);
        [, , $period, $item] = $this->setupChain(
            ['deadline' => $pastDate],
            ['status' => Status::Amber],
        );

        $this->service->process($period, [
            $item->id => ['days_spent' => 0.5, 'completed' => false],
        ], []);

        // Red beats amber when deadline is in the past.
        $this->assertSame(Status::Red, $item->fresh()->status);
    }

    // ---------- Ad-hoc items ------------------------------------------------

    public function test_ad_hoc_item_is_created_as_completed_green(): void
    {
        [, , $period] = $this->setupChain();

        $this->service->process($period, [], [
            ['name' => 'Emergency server fix', 'days_spent' => 0.5, 'notes' => 'Apache restart'],
        ]);

        $adHoc = $period->items()->whereNull('deliverable_id')->first();
        $this->assertNotNull($adHoc);
        $this->assertSame('Emergency server fix', $adHoc->ad_hoc_name);
        $this->assertEqualsWithDelta(0.5, (float) $adHoc->days_spent, 0.01);
        $this->assertSame(Status::Green, $adHoc->status);
        $this->assertNotNull($adHoc->completed_at);
    }

    public function test_blank_name_ad_hoc_rows_are_dropped(): void
    {
        [, , $period] = $this->setupChain();

        $this->service->process($period, [], [
            ['name' => '   ', 'days_spent' => 1.0],  // blank name
            ['name' => 'Real item', 'days_spent' => 0.5],
        ]);

        $this->assertSame(1, $period->items()->whereNull('deliverable_id')->count());
    }

    // ---------- Roll forward ------------------------------------------------

    public function test_roll_forward_copies_incomplete_items_to_next_week(): void
    {
        [$user, , $period, $item] = $this->setupChain();

        // One incomplete (the seeded item), and one already-complete.
        $deliverable2 = Deliverable::factory()->create([
            'project_id' => $item->deliverable->project_id,
        ]);
        $completedItem = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable2->id,
            'allocated_days' => 1.0,
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
        $this->assertEqualsWithDelta((float) $item->allocated_days, (float) $copied->first()->allocated_days, 0.01);
        $this->assertSame(Status::Red, $copied->first()->status);
        $this->assertEqualsWithDelta(0.0, (float) $copied->first()->days_spent, 0.01);
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
}
