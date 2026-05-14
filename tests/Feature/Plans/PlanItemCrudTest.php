<?php

namespace Tests\Feature\Plans;

use App\Enums\PlanKind;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanItemCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: build a fully-owned chain (user → client → project → deliverable)
     * and the current weekly period for the same user.
     *
     * Returns [User $user, Deliverable $deliverable, PlanPeriod $period].
     */
    private function makeOwnedChain(): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $client->id,
        ]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        return [$user, $deliverable, $period];
    }

    public function test_user_can_add_a_deliverable_to_their_plan(): void
    {
        [$user, $deliverable, $period] = $this->makeOwnedChain();

        $response = $this->actingAs($user)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $period->id,
                'deliverable_id' => $deliverable->id,
                'allocated_days' => 2.5,
            ]);

        $response->assertRedirect();
        $item = PlanItem::where('plan_period_id', $period->id)->firstOrFail();
        $this->assertSame($deliverable->id, $item->deliverable_id);
        $this->assertEqualsWithDelta(2.5, (float) $item->allocated_days, 0.01);
    }

    public function test_cannot_add_same_deliverable_twice_to_one_period(): void
    {
        [$user, $deliverable, $period] = $this->makeOwnedChain();
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_days' => 1.0,
        ]);

        $response = $this->actingAs($user)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $period->id,
                'deliverable_id' => $deliverable->id,
                'allocated_days' => 1.0,
            ]);

        $response->assertSessionHasErrors('deliverable_id');
        $this->assertSame(1, PlanItem::where('plan_period_id', $period->id)->count());
    }

    public function test_allocated_days_must_be_half_day_increment(): void
    {
        [$user, $deliverable, $period] = $this->makeOwnedChain();

        $response = $this->actingAs($user)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $period->id,
                'deliverable_id' => $deliverable->id,
                'allocated_days' => 1.3,
            ]);

        $response->assertSessionHasErrors('allocated_days');
    }

    public function test_cannot_add_deliverable_owned_by_someone_else(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $projectOfB = Project::factory()->create(['owner_id' => $userB->id]);
        $deliverableOfB = Deliverable::factory()->create(['project_id' => $projectOfB->id]);
        $periodOfA = PlanPeriod::findOrCreateCurrentFor($userA, PlanKind::Weekly);

        $response = $this->actingAs($userA)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $periodOfA->id,
                'deliverable_id' => $deliverableOfB->id,
                'allocated_days' => 1.0,
            ]);

        $response->assertSessionHasErrors('deliverable_id');
    }

    public function test_cannot_add_to_someone_elses_plan_period(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $periodOfB = PlanPeriod::findOrCreateCurrentFor($userB, PlanKind::Weekly);
        $clientA = Client::factory()->create(['owner_id' => $userA->id]);
        $projectA = Project::factory()->create([
            'owner_id' => $userA->id, 'client_id' => $clientA->id,
        ]);
        $deliverableA = Deliverable::factory()->create(['project_id' => $projectA->id]);

        $response = $this->actingAs($userA)
            ->from('/plans/weekly')
            ->post('/plan-items', [
                'plan_period_id' => $periodOfB->id, // someone else's period
                'deliverable_id' => $deliverableA->id,
                'allocated_days' => 1.0,
            ]);

        $response->assertSessionHasErrors('plan_period_id');
    }

    public function test_user_can_update_allocated_days(): void
    {
        [$user, $deliverable, $period] = $this->makeOwnedChain();
        $item = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_days' => 1.0,
        ]);

        $this->actingAs($user)
            ->from('/plans/weekly')
            ->put("/plan-items/{$item->id}", ['allocated_days' => 3.5]);

        $this->assertEqualsWithDelta(3.5, (float) $item->fresh()->allocated_days, 0.01);
    }

    public function test_cannot_update_someone_elses_item(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $periodOfB = PlanPeriod::findOrCreateCurrentFor($userB, PlanKind::Weekly);
        $deliverableOfB = Deliverable::factory()->create([
            'project_id' => Project::factory()->create(['owner_id' => $userB->id])->id,
        ]);
        $item = PlanItem::factory()->create([
            'plan_period_id' => $periodOfB->id,
            'deliverable_id' => $deliverableOfB->id,
            'allocated_days' => 1.0,
        ]);

        $response = $this->actingAs($userA)
            ->put("/plan-items/{$item->id}", ['allocated_days' => 99.0]);

        $response->assertForbidden();
        $this->assertEqualsWithDelta(1.0, (float) $item->fresh()->allocated_days, 0.01);
    }

    public function test_user_can_remove_their_plan_item(): void
    {
        [$user, $deliverable, $period] = $this->makeOwnedChain();
        $item = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
        ]);

        $this->actingAs($user)
            ->from('/plans/weekly')
            ->delete("/plan-items/{$item->id}");

        $this->assertModelMissing($item);
    }

    public function test_cannot_remove_someone_elses_item(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $periodOfB = PlanPeriod::findOrCreateCurrentFor($userB, PlanKind::Weekly);
        $item = PlanItem::factory()->create(['plan_period_id' => $periodOfB->id]);

        $response = $this->actingAs($userA)->delete("/plan-items/{$item->id}");

        $response->assertForbidden();
        $this->assertModelExists($item);
    }

    public function test_capacity_and_overunder_reflect_plan_items(): void
    {
        $user = User::factory()->create([
            'weekly_capacity_days' => 5.0,
        ]);
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $project = Project::factory()->create(['owner_id' => $user->id, 'client_id' => $client->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        // Plan 6 days against a 5-day capacity → +1 over.
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => Deliverable::factory()->create(['project_id' => $project->id])->id,
            'allocated_days' => 3.0,
        ]);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => Deliverable::factory()->create(['project_id' => $project->id])->id,
            'allocated_days' => 3.0,
        ]);

        $period->refresh();
        $this->assertEqualsWithDelta(6.0, $period->totalAllocated(), 0.01);
        $this->assertEqualsWithDelta(5.0, $period->capacity(), 0.01);
        $this->assertEqualsWithDelta(1.0, $period->overUnder(), 0.01);
    }
}
