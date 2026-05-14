<?php

namespace Tests\Feature\Plans;

use App\Enums\PlanKind;
use App\Models\PlanPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify the calendar arithmetic and the find-or-create idempotency of
 * PlanPeriod. Pin these so a refactor of the period boundaries can't
 * silently shift everything by a day.
 */
class PlanPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_bounds_are_monday_to_sunday(): void
    {
        // Wednesday 13 May 2026 → week is Mon 11 May → Sun 17 May
        $now = CarbonImmutable::create(2026, 5, 13, 12, 0, 0);

        [$start, $end] = PlanPeriod::boundsFor(PlanKind::Weekly, $now);

        $this->assertSame('2026-05-11', $start->toDateString());
        $this->assertSame('2026-05-17', $end->toDateString());
        $this->assertSame(1, $start->dayOfWeek); // Monday in Carbon
        $this->assertSame(0, $end->dayOfWeek);   // Sunday in Carbon
    }

    public function test_monthly_bounds_are_first_to_last_of_month(): void
    {
        $now = CarbonImmutable::create(2026, 5, 13);

        [$start, $end] = PlanPeriod::boundsFor(PlanKind::Monthly, $now);

        $this->assertSame('2026-05-01', $start->toDateString());
        $this->assertSame('2026-05-31', $end->toDateString());
    }

    public function test_quarterly_bounds_are_current_month_through_two_months_ahead(): void
    {
        // 14 May 2026 → quarter = 1 May → 31 July
        $now = CarbonImmutable::create(2026, 5, 14);

        [$start, $end] = PlanPeriod::boundsFor(PlanKind::Quarterly, $now);

        $this->assertSame('2026-05-01', $start->toDateString());
        $this->assertSame('2026-07-31', $end->toDateString());
    }

    public function test_find_or_create_is_idempotent(): void
    {
        $user = User::factory()->create();

        $a = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $b = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, PlanPeriod::where('owner_id', $user->id)->count());
    }

    public function test_find_or_create_returns_distinct_periods_per_kind(): void
    {
        $user = User::factory()->create();

        $w = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $m = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Monthly);
        $q = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Quarterly);

        $this->assertNotSame($w->id, $m->id);
        $this->assertNotSame($m->id, $q->id);
        $this->assertSame(PlanKind::Weekly, $w->kind);
        $this->assertSame(PlanKind::Monthly, $m->kind);
        $this->assertSame(PlanKind::Quarterly, $q->kind);
    }

    public function test_capacity_uses_user_weekly_value(): void
    {
        $user = User::factory()->create([
            'weekly_capacity_days' => 4.0,
            'monthly_capacity_days' => 18.0,
        ]);

        $weekly = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $monthly = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Monthly);
        $quarterly = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Quarterly);

        $this->assertEqualsWithDelta(4.0, $weekly->capacity(), 0.01);
        $this->assertEqualsWithDelta(18.0, $monthly->capacity(), 0.01);
        // Quarterly = 3 × monthly
        $this->assertEqualsWithDelta(54.0, $quarterly->capacity(), 0.01);
    }
}
