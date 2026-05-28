<?php

namespace Tests\Feature\Plans;

use App\Enums\PlanKind;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_weekly_plan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/plans/weekly');

        $response->assertOk();
        $response->assertSeeText('Weekly Plan');
    }

    public function test_user_can_view_monthly_plan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/plans/monthly');

        $response->assertOk();
        $response->assertSeeText('Monthly Plan');
    }

    public function test_user_can_view_quarterly_plan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/plans/quarterly');

        $response->assertOk();
        $response->assertSeeText('Quarterly Plan');
    }

    public function test_first_visit_creates_a_plan_period(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, PlanPeriod::where('owner_id', $user->id)->count());

        $this->actingAs($user)->get('/plans/weekly')->assertOk();

        $this->assertSame(1, PlanPeriod::where('owner_id', $user->id)->count());
        $this->assertSame(
            PlanKind::Weekly,
            PlanPeriod::where('owner_id', $user->id)->first()->kind,
        );
    }

    public function test_repeated_visits_do_not_duplicate_the_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/plans/weekly');
        $this->actingAs($user)->get('/plans/weekly');
        $this->actingAs($user)->get('/plans/weekly');

        $this->assertSame(1, PlanPeriod::where('owner_id', $user->id)->count());
    }

    public function test_admin_is_redirected_away_from_plans(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/plans/weekly');

        $response->assertRedirect(route('admin.users.index'));
    }

    public function test_viewer_is_redirected_away_from_plans_for_now(): void
    {
        $viewer = User::factory()->viewer()->create();

        $response = $this->actingAs($viewer)->get('/plans/weekly');

        $response->assertRedirect(route('viewer.dashboard'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/plans/weekly');

        $response->assertRedirect(route('login'));
    }

    public function test_weekly_plan_shows_period_scoped_hours_spent(): void
    {
        $user = User::factory()->create(['weekly_capacity_hours' => 40.0]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Acme OAuth flow',
        ]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_hours' => 16.0,
        ]);

        // Two logs inside the period — should be counted.
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'log_date' => $period->starts_on, 'hours' => 2.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'log_date' => $period->ends_on, 'hours' => 3.5,
        ]);
        // A log outside the period — should NOT show on this week's spent.
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'log_date' => CarbonImmutable::parse($period->starts_on)->subDay(),
            'hours' => 99.0,
        ]);

        $response = $this->actingAs($user)->get('/plans/weekly');

        $response->assertOk();
        // Period-scoped spent = 5.5h (0.7d), formatted hours-leading.
        $response->assertSee('5.5h (0.7d)');
        // Allocated reads days-leading after M8e.
        $response->assertSee('2d (16h)');
    }

    public function test_capacity_widget_shows_days_leading_format(): void
    {
        $user = User::factory()->create(['weekly_capacity_hours' => 40.0]);

        $response = $this->actingAs($user)->get('/plans/weekly');

        $response->assertOk();
        // 40h capacity = 5d (40h) in days-leading display.
        $response->assertSee('5d (40h)');
    }
}
