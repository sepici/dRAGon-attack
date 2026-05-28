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

class WriteApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUserWithAbilities(array $abilities = ['*']): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, $abilities);
        return $user;
    }

    // ---------- POST /time-logs (the agent's bread-and-butter) ----------

    public function test_post_time_log_with_deliverable_id_creates_log_today(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);

        $response = $this->postJson('/api/v1/time-logs', [
            'hours' => 2.5,
            'deliverable_id' => $d->id,
            'notes' => 'OAuth wiring',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.deliverable_id', $d->id);
        $response->assertJsonPath('data.notes', 'OAuth wiring');

        $log = TimeLog::firstOrFail();
        $this->assertSame($user->id, $log->owner_id);
        $this->assertEqualsWithDelta(2.5, (float) $log->hours, 0.01);
        $this->assertSame(now()->toDateString(), $log->log_date->toDateString());
    }

    public function test_post_time_log_resolves_fuzzy_deliverable_name(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Acme OAuth flow',
        ]);

        $response = $this->postJson('/api/v1/time-logs', [
            'hours' => 1.5,
            'deliverable_name' => 'acme oauth',
            'date' => 'yesterday',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.deliverable_id', $d->id);
        $response->assertJsonPath('data.log_date', now()->subDay()->toDateString());
    }

    public function test_post_time_log_returns_helpful_error_when_name_no_match(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Acme OAuth flow',
        ]);

        $response = $this->postJson('/api/v1/time-logs', [
            'hours' => 1.0,
            'deliverable_name' => 'totally-not-a-thing',
        ]);

        $response->assertStatus(422);
        $errors = $response->json('errors.deliverable_name');
        $this->assertNotNull($errors);
        // The error message lists candidate deliverables to nudge the agent
        // toward a working call on the retry.
        $this->assertStringContainsString('Acme OAuth flow', $errors[0]);
    }

    public function test_post_time_log_ad_hoc(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);

        $response = $this->postJson('/api/v1/time-logs', [
            'hours' => 0.5,
            'ad_hoc_name' => 'Cloudways nginx restart',
            'date' => 'today',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.is_ad_hoc', true);
        $response->assertJsonPath('data.ad_hoc_name', 'Cloudways nginx restart');
        $response->assertJsonPath('data.deliverable_id', null);
    }

    public function test_post_time_log_rejects_both_deliverable_and_ad_hoc(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);

        $this->postJson('/api/v1/time-logs', [
            'hours' => 1.0,
            'deliverable_id' => $d->id,
            'ad_hoc_name' => 'Both',
        ])->assertStatus(422);
    }

    public function test_post_time_log_rejects_deliverable_owned_by_someone_else(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);
        $other = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $other->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);

        $this->postJson('/api/v1/time-logs', [
            'hours' => 1.0,
            'deliverable_id' => $d->id,
        ])->assertStatus(422);
        $this->assertSame(0, TimeLog::count());
    }

    public function test_post_time_log_requires_time_logs_write_ability(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_READ]);

        $this->postJson('/api/v1/time-logs', [
            'hours' => 1.0,
            'ad_hoc_name' => 'X',
        ])->assertForbidden();
    }

    // ---------- PUT /time-logs/{id} ----------

    public function test_put_time_log_patches_fields(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);
        $log = TimeLog::factory()->adHoc('Old')->create([
            'owner_id' => $user->id, 'hours' => 1.0,
            'log_date' => '2026-05-15',
        ]);

        $response = $this->putJson("/api/v1/time-logs/{$log->id}", [
            'hours' => 3.5,
            'notes' => 'updated',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.hours', 3.5);
        $response->assertJsonPath('data.notes', 'updated');
        // log_date was untouched.
        $this->assertSame('2026-05-15', $log->fresh()->log_date->toDateString());
    }

    public function test_put_time_log_404s_for_other_owner(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);
        $someone = User::factory()->create();
        $log = TimeLog::factory()->adHoc('private')->create(['owner_id' => $someone->id]);

        $this->putJson("/api/v1/time-logs/{$log->id}", ['hours' => 4.0])
            ->assertNotFound();
    }

    // ---------- DELETE /time-logs/{id} ----------

    public function test_delete_time_log(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TIME_LOGS_WRITE]);
        $log = TimeLog::factory()->adHoc('x')->create(['owner_id' => $user->id]);

        $this->deleteJson("/api/v1/time-logs/{$log->id}")
            ->assertNoContent();
        $this->assertNull($log->fresh());
    }

    // ---------- /clients ----------

    public function test_post_client(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);

        $response = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Acme Ltd',
            'email' => 'hi@acme.example',
            'notes' => 'Big fish.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.legal_name', 'Acme Ltd');
        $this->assertSame($user->id, Client::firstOrFail()->owner_id);
    }

    public function test_clients_writes_require_tracker_write(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $this->postJson('/api/v1/clients', ['legal_name' => 'X'])->assertForbidden();
    }

    // ---------- /projects ----------

    public function test_post_project_requires_owned_client(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $mine = Client::factory()->create(['owner_id' => $user->id]);
        $someoneElses = Client::factory()->create();

        $this->postJson('/api/v1/projects', [
            'client_id' => $someoneElses->id,
            'name' => 'Stolen',
        ])->assertStatus(422);

        $response = $this->postJson('/api/v1/projects', [
            'client_id' => $mine->id,
            'name' => 'Mine',
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Mine');
    }

    // ---------- /deliverables ----------

    public function test_post_deliverable_defaults_to_red(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);

        $response = $this->postJson('/api/v1/deliverables', [
            'project_id' => $project->id,
            'name' => 'Spec',
            'target_hours' => 16.0,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'R');
        $response->assertJsonPath('data.target_hours', 16);
        $response->assertJsonPath('data.target_days', 2);
    }

    public function test_put_deliverable_updates_subset(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Old',
            'target_hours' => 8.0,
        ]);

        $response = $this->putJson("/api/v1/deliverables/{$d->id}", [
            'name' => 'New',
            'status' => 'A',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New');
        $response->assertJsonPath('data.status', 'A');
        // target_hours unchanged.
        $this->assertEqualsWithDelta(8.0, (float) $d->fresh()->target_hours, 0.01);
    }

    public function test_delete_deliverable(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);

        $this->deleteJson("/api/v1/deliverables/{$d->id}")->assertNoContent();
        $this->assertNull($d->fresh());
    }

    // ---------- /plan-items ----------

    public function test_post_plan_item_with_period_kind_shortcut(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);

        // No explicit plan_period_id — should auto-resolve to this user's
        // current weekly period (creating it if needed).
        $response = $this->postJson('/api/v1/plan-items', [
            'period_kind' => 'weekly',
            'deliverable_id' => $d->id,
            'allocated_hours' => 16.0,
        ]);

        $response->assertStatus(201);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $this->assertSame(1, $period->items()->count());
        $response->assertJsonPath('data.plan_period_id', $period->id);
        $response->assertJsonPath('data.allocated_hours', 16);
    }

    public function test_post_plan_item_blocks_duplicate(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $d->id,
        ]);

        $this->postJson('/api/v1/plan-items', [
            'plan_period_id' => $period->id,
            'deliverable_id' => $d->id,
            'allocated_hours' => 8.0,
        ])->assertStatus(422);
    }

    public function test_put_plan_item_can_set_completed_at(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $item = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $d->id,
            'allocated_hours' => 8.0,
        ]);

        $stamp = CarbonImmutable::now()->toIso8601String();
        $response = $this->putJson("/api/v1/plan-items/{$item->id}", [
            'status' => 'G',
            'completed_at' => $stamp,
        ]);

        $response->assertOk();
        $this->assertSame(Status::Green, $item->fresh()->status);
        $this->assertNotNull($item->fresh()->completed_at);
    }

    public function test_delete_plan_item(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $d = Deliverable::factory()->create(['project_id' => $project->id]);
        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $item = PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $d->id,
        ]);

        $this->deleteJson("/api/v1/plan-items/{$item->id}")->assertNoContent();
        $this->assertNull($item->fresh());
    }
}
