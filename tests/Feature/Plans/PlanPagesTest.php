<?php

namespace Tests\Feature\Plans;

use App\Enums\PlanKind;
use App\Models\PlanPeriod;
use App\Models\User;
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
}
