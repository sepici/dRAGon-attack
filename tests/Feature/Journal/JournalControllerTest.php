<?php

namespace Tests\Feature\Journal;

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

class JournalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_today_redirects_to_dated_url(): void
    {
        $user = User::factory()->create();
        $today = CarbonImmutable::now()->toDateString();

        $response = $this->actingAs($user)->get('/journal');

        $response->assertRedirect("/journal/{$today}");
    }

    public function test_show_renders_for_user_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/journal/2026-05-15');

        $response->assertOk();
        $response->assertSeeText('Daily Journal');
    }

    public function test_admin_blocked_from_journal(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/journal/2026-05-15')->assertRedirect();
    }

    public function test_viewer_blocked_from_journal(): void
    {
        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->get('/journal/2026-05-15')->assertRedirect();
    }

    public function test_invalid_date_format_returns_404(): void
    {
        $user = User::factory()->create();
        // Should 404 — route regex rejects this before we even hit the action.
        $this->actingAs($user)->get('/journal/15-05-2026')->assertNotFound();
        $this->actingAs($user)->get('/journal/2026-13-01')->assertNotFound();
    }

    public function test_store_creates_logs_for_planned_items(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
        ]);

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($user)->post("/journal/{$date}", [
            'items' => [
                $deliverable->id => ['hours' => 2.5, 'notes' => 'Spec drafting'],
            ],
        ]);

        $response->assertRedirect("/journal/{$date}");
        $this->assertSame(1, TimeLog::count());
        $log = TimeLog::first();
        $this->assertSame($deliverable->id, $log->deliverable_id);
        $this->assertEqualsWithDelta(2.5, (float) $log->hours, 0.01);
    }

    public function test_store_rejects_deliverable_owned_by_someone_else(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $projectOfB = Project::factory()->create(['owner_id' => $userB->id]);
        $deliverableOfB = Deliverable::factory()->create(['project_id' => $projectOfB->id]);

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($userA)
            ->from("/journal/{$date}")
            ->post("/journal/{$date}", [
                'items' => [
                    $deliverableOfB->id => ['hours' => 1.0],
                ],
            ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, TimeLog::count());
    }

    public function test_store_rejects_ad_hoc_row_belonging_to_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $bsAdHoc = TimeLog::factory()->adHoc('B private')->create([
            'owner_id' => $userB->id,
            'log_date' => CarbonImmutable::now()->toDateString(),
            'hours' => 1.0,
        ]);

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($userA)
            ->from("/journal/{$date}")
            ->post("/journal/{$date}", [
                'ad_hoc' => [
                    ['id' => $bsAdHoc->id, 'name' => 'Steal it', 'hours' => 4.0],
                ],
            ]);

        $response->assertSessionHasErrors('ad_hoc');
        $bsAdHoc->refresh();
        $this->assertSame('B private', $bsAdHoc->ad_hoc_name);
        $this->assertEqualsWithDelta(1.0, (float) $bsAdHoc->hours, 0.01);
    }

    public function test_half_hour_increment_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        $date = CarbonImmutable::now()->toDateString();

        $response = $this->actingAs($user)
            ->from("/journal/{$date}")
            ->post("/journal/{$date}", [
                'items' => [
                    $deliverable->id => ['hours' => 1.3], // not 0.5-multiple
                ],
            ]);

        $response->assertSessionHasErrors('items.' . $deliverable->id . '.hours');
    }

    public function test_existing_log_prefills_in_the_form(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Magnolia API client',
        ]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
        ]);

        $today = CarbonImmutable::now()->toDateString();
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => $today,
            'hours' => 3.5,
            'notes' => 'Hooked up auth.',
        ]);

        $response = $this->actingAs($user)->get("/journal/{$today}");

        $response->assertOk();
        $response->assertSee('Magnolia API client');
        $response->assertSee('Hooked up auth.');
        $response->assertSee('3.5');
    }
}
