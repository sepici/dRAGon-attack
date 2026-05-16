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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP-level tests for the /review endpoints. The transactional behaviour
 * itself is exhaustively covered in ReviewServiceTest; here we just verify
 * the controller wires inputs through correctly and enforces role access.
 */
class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_review_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/review');

        $response->assertOk();
        $response->assertSeeText('Weekly Review');
    }

    public function test_first_visit_creates_the_weekly_period(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, PlanPeriod::where('owner_id', $user->id)->count());

        $this->actingAs($user)->get('/review')->assertOk();

        $this->assertSame(1, PlanPeriod::where('owner_id', $user->id)->count());
    }

    public function test_admin_redirected_away_from_review(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get('/review');
        $response->assertRedirect(route('admin.users.index'));
    }

    public function test_viewer_redirected_away_from_review(): void
    {
        $viewer = User::factory()->viewer()->create();
        $response = $this->actingAs($viewer)->get('/review');
        $response->assertRedirect(route('viewer.dashboard'));
    }

    public function test_submitting_review_marks_item_complete_and_increments_deliverable(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'hours_spent' => 0,
        ]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $item = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_hours' => 16.0,
        ]);

        $response = $this->actingAs($user)->post('/review', [
            'items' => [
                $item->id => [
                    'completed' => '1',
                    'hours_spent' => 12.0,
                    'notes' => 'finished',
                ],
            ],
        ]);

        $response->assertRedirect(route('review.show'));

        $item->refresh();
        $this->assertSame(Status::Green, $item->status);
        $this->assertNotNull($item->completed_at);
        $this->assertEqualsWithDelta(12.0, (float) $deliverable->fresh()->hours_spent, 0.01);
    }

    public function test_submitting_ad_hoc_row_creates_completed_plan_item(): void
    {
        $user = User::factory()->create();
        // No planned items needed for this test.

        $this->actingAs($user)->post('/review', [
            'ad_hoc' => [
                ['name' => 'Server outage', 'hours_spent' => 4.0, 'notes' => 'Cloudways nginx restart'],
            ],
        ]);

        $period = PlanPeriod::where('owner_id', $user->id)->firstOrFail();
        $adHoc = $period->items()->whereNull('deliverable_id')->firstOrFail();
        $this->assertSame('Server outage', $adHoc->ad_hoc_name);
        $this->assertSame(Status::Green, $adHoc->status);
    }

    public function test_half_hour_increment_validation_on_hours_spent(): void
    {
        $user = User::factory()->create();
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $item = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => Deliverable::factory()->create(['project_id' => $project->id])->id,
        ]);

        $response = $this->actingAs($user)
            ->from('/review')
            ->post('/review', [
                'items' => [
                    $item->id => ['hours_spent' => 1.3, 'completed' => '1'],
                ],
            ]);

        $response->assertSessionHasErrors('items.' . $item->id . '.hours_spent');
    }

    public function test_roll_forward_button_copies_incomplete_into_next_week(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_hours' => 8.0,
        ]);

        $response = $this->actingAs($user)->post('/review/roll-forward');

        $response->assertRedirect(route('review.show'));

        $next = PlanPeriod::where('owner_id', $user->id)
            ->where('kind', 'weekly')
            ->where('id', '!=', $period->id)
            ->first();
        $this->assertNotNull($next);
        $this->assertSame(1, $next->items()->count());
        $this->assertSame($deliverable->id, $next->items()->first()->deliverable_id);
    }
}
