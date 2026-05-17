<?php

namespace Tests\Feature\Seeders;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Database\Seeders\DummyDeliverablesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DummyDeliverablesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function ownerUser(): User
    {
        return User::factory()->create(['email' => 'sandbox-owner@example.com']);
    }

    public function test_seeder_bails_when_no_user_exists(): void
    {
        // No user rows in the DB at all.
        $this->seed(DummyDeliverablesSeeder::class);

        $this->assertSame(0, Client::count());
        $this->assertSame(0, Project::count());
        $this->assertSame(0, Deliverable::count());
    }

    public function test_seeder_picks_env_owner_when_set(): void
    {
        $a = User::factory()->create(['email' => 'a@example.com']);
        $b = User::factory()->create(['email' => 'b@example.com']);

        putenv('SEED_OWNER_EMAIL=b@example.com');
        try {
            $this->seed(DummyDeliverablesSeeder::class);
        } finally {
            putenv('SEED_OWNER_EMAIL');
        }

        $project = Project::firstOrFail();
        $this->assertSame($b->id, $project->owner_id);
    }

    public function test_seeder_falls_back_to_first_user_when_env_unset(): void
    {
        $first = User::factory()->create(['email' => 'first@example.com']);
        User::factory()->create(['email' => 'second@example.com']);

        $this->seed(DummyDeliverablesSeeder::class);

        $project = Project::firstOrFail();
        $this->assertSame($first->id, $project->owner_id);
    }

    public function test_seeder_creates_sandbox_client_project_and_deliverables(): void
    {
        $owner = $this->ownerUser();

        $this->seed(DummyDeliverablesSeeder::class);

        $this->assertSame(1, Client::where('owner_id', $owner->id)->count());
        $client = Client::firstOrFail();
        $this->assertSame('Sandbox', $client->legal_name);

        $this->assertSame(1, Project::where('owner_id', $owner->id)->count());
        $project = Project::firstOrFail();
        $this->assertSame('Imported deliverables', $project->name);
        $this->assertSame($client->id, $project->client_id);

        // 42 rows in the source spreadsheet.
        $this->assertSame(42, Deliverable::count());
    }

    public function test_seeded_deliverable_carries_id_prefix_and_correct_hours(): void
    {
        $this->ownerUser();
        $this->seed(DummyDeliverablesSeeder::class);

        // CLN001 had target 1.5 days, actual 1.0 day, MoSCoW M, RAG A.
        $cln001 = Deliverable::where('name', 'like', '[CLN001] %')->firstOrFail();
        $this->assertSame('[CLN001] Clonallon Proposal', $cln001->name);
        $this->assertEqualsWithDelta(12.0, (float) $cln001->target_hours, 0.01); // 1.5 × 8
        $this->assertSame(Status::Amber, $cln001->status);
        $this->assertSame(Moscow::Must, $cln001->moscow);
        $this->assertNull($cln001->deadline);

        // Actual 1.0 day → one time_log of 8.0 hours.
        $this->assertSame(1, $cln001->timeLogs()->count());
        $log = $cln001->timeLogs()->first();
        $this->assertEqualsWithDelta(8.0, (float) $log->hours, 0.01);
        $this->assertSame('2026-05-11', $log->log_date->toDateString());

        // Derived hours_spent should reflect the synthetic log.
        $this->assertEqualsWithDelta(8.0, (float) $cln001->fresh()->hours_spent, 0.01);
    }

    public function test_seeded_deliverable_with_no_actual_has_no_time_log(): void
    {
        $this->ownerUser();
        $this->seed(DummyDeliverablesSeeder::class);

        // AOC002 had target 1.0 day, actual blank.
        $aoc002 = Deliverable::where('name', 'like', '[AOC002] %')->firstOrFail();
        $this->assertSame(0, $aoc002->timeLogs()->count());
        $this->assertEqualsWithDelta(0.0, (float) $aoc002->fresh()->hours_spent, 0.01);
    }

    public function test_seeder_skips_blank_target_with_zero_hours(): void
    {
        $this->ownerUser();
        $this->seed(DummyDeliverablesSeeder::class);

        // TCC007 had blank Target — should land as 0.0 target_hours.
        $tcc007 = Deliverable::where('name', 'like', '[TCC007] %')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $tcc007->target_hours, 0.01);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->ownerUser();
        $this->seed(DummyDeliverablesSeeder::class);
        $firstRunDeliverables = Deliverable::count();
        $firstRunLogs = TimeLog::count();

        // Run a second time — should not duplicate.
        $this->seed(DummyDeliverablesSeeder::class);

        $this->assertSame($firstRunDeliverables, Deliverable::count());
        $this->assertSame($firstRunLogs, TimeLog::count());
    }

    public function test_status_defaults_to_red_when_rag_is_blank(): void
    {
        $this->ownerUser();
        $this->seed(DummyDeliverablesSeeder::class);

        // AOC001 had blank RAG.
        $aoc001 = Deliverable::where('name', 'like', '[AOC001] %')->firstOrFail();
        $this->assertSame(Status::Red, $aoc001->status);
        $this->assertNull($aoc001->moscow);
    }

    public function test_duplicate_id_in_source_is_imported_as_two_deliverables(): void
    {
        $this->ownerUser();
        $this->seed(DummyDeliverablesSeeder::class);

        // TCC017 appears twice in the source — dedupe is on name, not ID.
        $tcc017Rows = Deliverable::where('name', 'like', '[TCC017] %')->get();
        $this->assertCount(2, $tcc017Rows);
    }
}
