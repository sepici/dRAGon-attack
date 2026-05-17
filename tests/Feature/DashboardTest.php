<?php

namespace Tests\Feature;

use App\Enums\PlanKind;
use App\Enums\Status;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Dashboard');
    }

    public function test_admin_redirected_away_from_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.users.index'));
    }

    public function test_viewer_redirected_away_from_dashboard(): void
    {
        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->get('/dashboard')->assertRedirect(route('viewer.dashboard'));
    }

    public function test_capacity_cards_render_days_leading(): void
    {
        $user = User::factory()->create([
            'weekly_capacity_hours' => 40.0,
            'monthly_capacity_hours' => 160.0,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // Weekly capacity card shows "5d (40h)".
        $response->assertSee('5d (40h)');
        // Monthly card shows "20d (160h)".
        $response->assertSee('20d (160h)');
        // Quarterly = 3 × monthly = 480h = 60d.
        $response->assertSee('60d (480h)');
    }

    public function test_capacity_cards_reflect_allocations(): void
    {
        $user = User::factory()->create(['weekly_capacity_hours' => 40.0]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_hours' => 24.0,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // 24h allocated / 40h capacity = 16h headroom, days-leading.
        $response->assertSee('3d (24h)');
        $response->assertSee('2d (16h) headroom');
    }

    public function test_status_counts_reflect_deliverables(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        Deliverable::factory()->count(3)->create(['project_id' => $project->id, 'status' => Status::Red]);
        Deliverable::factory()->count(2)->create(['project_id' => $project->id, 'status' => Status::Amber]);
        Deliverable::factory()->count(1)->create(['project_id' => $project->id, 'status' => Status::Green]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('View all (6)');
    }

    public function test_recently_completed_shows_derived_hours_spent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Carbon Markets backend',
            'completed_at' => CarbonImmutable::now()->subHours(2),
        ]);

        // 5h of logged time on this deliverable → derived hours_spent.
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'hours' => 5.0,
            'log_date' => CarbonImmutable::now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Carbon Markets backend');
        // Spent is hours-leading: "5h (0.6d)".
        $response->assertSee('5h (0.6d)');
    }

    public function test_upcoming_deadlines_list_renders(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Due tomorrow',
            'deadline' => CarbonImmutable::now()->addDay(),
            'status' => Status::Amber,
        ]);
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Due in a month',
            'deadline' => CarbonImmutable::now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Due tomorrow');
        // Out of the 7-day window — should NOT appear in the "Due in the next 7 days" list.
        $response->assertDontSee('Due in a month');
    }
}
