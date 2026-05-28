<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PlanKind;
use App\Enums\Status;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\ApiAbility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReadApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: act as a user-role token holder with the given abilities.
     * Tracker abilities default to read+write for convenience in tests
     * not specifically about ability checks.
     */
    private function actingAsUserWithAbilities(array $abilities = ['*']): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, $abilities);
        return $user;
    }

    // ---------- /clients ----------

    public function test_clients_index_returns_only_owners_clients(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        Client::factory()->create(['owner_id' => $user->id, 'legal_name' => 'Acme']);
        Client::factory()->create(['owner_id' => $user->id, 'legal_name' => 'Beta Ltd']);
        // Other user's client — should NOT appear.
        Client::factory()->create(['legal_name' => 'Strangers Inc']);

        $response = $this->getJson('/api/v1/clients');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['legal_name' => 'Acme']);
        $response->assertJsonFragment(['legal_name' => 'Beta Ltd']);
        $response->assertJsonMissing(['legal_name' => 'Strangers Inc']);
    }

    public function test_clients_show_404s_for_other_users_record(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $other = Client::factory()->create();

        $this->getJson("/api/v1/clients/{$other->id}")->assertNotFound();
    }

    public function test_tracker_read_required_for_clients(): void
    {
        // Token without tracker:read shouldn't get in.
        $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_READ]);

        $this->getJson('/api/v1/clients')->assertForbidden();
    }

    // ---------- /projects ----------

    public function test_projects_index_supports_client_id_filter(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $clientA = Client::factory()->create(['owner_id' => $user->id]);
        $clientB = Client::factory()->create(['owner_id' => $user->id]);
        Project::factory()->create(['owner_id' => $user->id, 'client_id' => $clientA->id, 'name' => 'A1']);
        Project::factory()->create(['owner_id' => $user->id, 'client_id' => $clientA->id, 'name' => 'A2']);
        Project::factory()->create(['owner_id' => $user->id, 'client_id' => $clientB->id, 'name' => 'B1']);

        $response = $this->getJson("/api/v1/projects?client_id={$clientA->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['name' => 'A1']);
        $response->assertJsonFragment(['name' => 'A2']);
        $response->assertJsonMissing(['name' => 'B1']);
    }

    // ---------- /deliverables ----------

    public function test_deliverables_show_returns_derived_hours_spent(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Acme OAuth',
            'target_hours' => 16.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'hours' => 5.5,
            'log_date' => '2026-05-15',
        ]);

        $response = $this->getJson("/api/v1/deliverables/{$deliverable->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'name' => 'Acme OAuth',
                'target_hours' => 16.0,
                'target_days' => 2.0,
                'hours_spent' => 5.5,
                'days_spent' => 0.6875,
                'remaining_hours' => 10.5,
            ],
        ]);
    }

    public function test_deliverables_name_like_filter(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        Deliverable::factory()->create(['project_id' => $project->id, 'name' => 'Acme OAuth flow']);
        Deliverable::factory()->create(['project_id' => $project->id, 'name' => 'Acme migration']);
        Deliverable::factory()->create(['project_id' => $project->id, 'name' => 'Titan API client']);

        $response = $this->getJson('/api/v1/deliverables?name_like=acme');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_deliverables_status_and_completed_filters(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        Deliverable::factory()->create(['project_id' => $project->id, 'status' => Status::Amber, 'name' => 'A1']);
        Deliverable::factory()->create([
            'project_id' => $project->id, 'status' => Status::Green,
            'completed_at' => now(), 'name' => 'G1',
        ]);
        Deliverable::factory()->create(['project_id' => $project->id, 'status' => Status::Red, 'name' => 'R1']);

        $this->getJson('/api/v1/deliverables?status=A')->assertJsonCount(1, 'data')->assertJsonFragment(['name' => 'A1']);
        $this->getJson('/api/v1/deliverables?completed=true')->assertJsonCount(1, 'data')->assertJsonFragment(['name' => 'G1']);
    }

    // ---------- /plans/* ----------

    public function test_plans_weekly_returns_current_period_with_items_and_aggregates(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $user->update(['weekly_capacity_hours' => 40.0]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'name' => 'Spec X']);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $deliverable->id,
            'allocated_hours' => 16.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'hours' => 6.0, 'log_date' => $period->starts_on,
        ]);

        $response = $this->getJson('/api/v1/plans/weekly');

        $response->assertOk();
        $response->assertJsonPath('data.kind', 'weekly');

        // PHP's json_encode drops trailing zeros — 40.0 lands as 40 in the
        // JSON when there's no fractional part. Cast through (float) so the
        // assertion doesn't depend on serialize_precision.
        $data = $response->json('data');
        $this->assertEqualsWithDelta(40.0, (float) $data['capacity_hours'], 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $data['capacity_days'], 0.001);
        $this->assertEqualsWithDelta(16.0, (float) $data['allocated_hours'], 0.001);
        $this->assertEqualsWithDelta(6.0, (float) $data['spent_hours'], 0.001);

        $response->assertJsonPath('data.items.0.deliverable.name', 'Spec X');
        $this->assertEqualsWithDelta(6.0, (float) $data['items'][0]['hours_spent'], 0.001);
    }

    // ---------- /time-logs ----------

    public function test_time_logs_index_supports_relative_dates(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        $today = CarbonImmutable::now()->toDateString();
        $yesterday = CarbonImmutable::now()->subDay()->toDateString();
        $aWeekAgo = CarbonImmutable::now()->subDays(7)->toDateString();

        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'log_date' => $today, 'hours' => 1.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'log_date' => $yesterday, 'hours' => 2.0,
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id,
            'log_date' => $aWeekAgo, 'hours' => 99.0,
        ]);

        $this->getJson('/api/v1/time-logs?date=today')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['hours' => 1.0]);

        $this->getJson('/api/v1/time-logs?date=yesterday')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['hours' => 2.0]);

        $this->getJson("/api/v1/time-logs?from=yesterday&to=today")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_time_logs_index_filters_by_deliverable_and_ad_hoc(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_READ]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $deliverable->id, 'hours' => 1.0,
        ]);
        TimeLog::factory()->adHoc('Cloudways outage')->create([
            'owner_id' => $user->id, 'hours' => 0.5,
        ]);

        $this->getJson("/api/v1/time-logs?deliverable_id={$deliverable->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['hours' => 1.0, 'is_ad_hoc' => false]);

        $this->getJson('/api/v1/time-logs?ad_hoc=true')
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['ad_hoc_name' => 'Cloudways outage', 'is_ad_hoc' => true]);
    }

    public function test_time_logs_only_returns_owners_logs(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_READ]);
        $other = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $other->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);
        TimeLog::factory()->create(['owner_id' => $other->id, 'deliverable_id' => $d->id]);

        $this->getJson('/api/v1/time-logs')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_time_logs_requires_time_logs_read_ability(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $this->getJson('/api/v1/time-logs')->assertForbidden();
    }

    public function test_read_all_wildcard_grants_both_tracker_and_time_logs(): void
    {
        // When the user picks "read:all" in the UI, ApiAbility::expand turns
        // that into [read:all, tracker:read, time-logs:read]. Sanctum then
        // matches the atomic check.
        $this->actingAsUserWithAbilities(
            ApiAbility::expand([ApiAbility::READ_ALL])
        );
        $this->getJson('/api/v1/clients')->assertOk();
        $this->getJson('/api/v1/time-logs')->assertOk();
    }
}
