<?php

namespace Tests\Feature\Journal;

use App\Models\Deliverable;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\DailyJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    private DailyJournalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DailyJournalService();
    }

    /** @return array{0:User,1:Deliverable} */
    private function chain(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        return [$user, $deliverable];
    }

    // ---------- Deliverable rows ------------------------------------------

    public function test_new_deliverable_row_creates_a_time_log(): void
    {
        [$user, $deliverable] = $this->chain();

        $this->service->sync($user, '2026-05-15', [
            $deliverable->id => ['hours' => 2.5, 'notes' => 'Drafted the OAuth flow.'],
        ], []);

        $this->assertSame(1, TimeLog::count());
        $log = TimeLog::first();
        $this->assertSame($deliverable->id, $log->deliverable_id);
        $this->assertEqualsWithDelta(2.5, (float) $log->hours, 0.01);
        $this->assertSame('Drafted the OAuth flow.', $log->notes);
    }

    public function test_existing_deliverable_row_is_updated_not_duplicated(): void
    {
        [$user, $deliverable] = $this->chain();
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => '2026-05-15',
            'hours' => 1.0,
            'notes' => 'old',
        ]);

        $this->service->sync($user, '2026-05-15', [
            $deliverable->id => ['hours' => 3.5, 'notes' => 'new'],
        ], []);

        $this->assertSame(1, TimeLog::count());
        $log = TimeLog::first();
        $this->assertEqualsWithDelta(3.5, (float) $log->hours, 0.01);
        $this->assertSame('new', $log->notes);
    }

    public function test_zero_hours_deletes_an_existing_deliverable_row(): void
    {
        [$user, $deliverable] = $this->chain();
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => '2026-05-15',
            'hours' => 2.0,
        ]);
        $this->assertSame(1, TimeLog::count());

        $this->service->sync($user, '2026-05-15', [
            $deliverable->id => ['hours' => 0, 'notes' => null],
        ], []);

        $this->assertSame(0, TimeLog::count());
    }

    public function test_zero_hours_with_no_existing_row_does_nothing(): void
    {
        [$user, $deliverable] = $this->chain();

        $this->service->sync($user, '2026-05-15', [
            $deliverable->id => ['hours' => 0, 'notes' => null],
        ], []);

        $this->assertSame(0, TimeLog::count());
    }

    public function test_sync_only_touches_the_target_date(): void
    {
        [$user, $deliverable] = $this->chain();
        // Log on a different day — must NOT be affected.
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => '2026-05-14',
            'hours' => 1.0,
        ]);

        $this->service->sync($user, '2026-05-15', [
            $deliverable->id => ['hours' => 4.0, 'notes' => null],
        ], []);

        $this->assertSame(2, TimeLog::count());
        $may14 = TimeLog::whereDate('log_date', '2026-05-14')->first();
        $this->assertEqualsWithDelta(1.0, (float) $may14->hours, 0.01);
    }

    // ---------- Ad-hoc rows ----------------------------------------------

    public function test_new_ad_hoc_row_creates_a_log(): void
    {
        [$user] = $this->chain();

        $this->service->sync($user, '2026-05-15', [], [
            ['id' => null, 'name' => 'Server outage', 'hours' => 0.5, 'notes' => 'Cloudways nginx'],
        ]);

        $log = TimeLog::whereNull('deliverable_id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Server outage', $log->ad_hoc_name);
        $this->assertEqualsWithDelta(0.5, (float) $log->hours, 0.01);
    }

    public function test_ad_hoc_row_with_zero_hours_is_not_created(): void
    {
        [$user] = $this->chain();

        $this->service->sync($user, '2026-05-15', [], [
            ['id' => null, 'name' => 'Half-typed', 'hours' => 0, 'notes' => null],
        ]);

        $this->assertSame(0, TimeLog::count());
    }

    public function test_existing_ad_hoc_row_is_updated_by_id(): void
    {
        [$user] = $this->chain();
        $log = TimeLog::factory()->adHoc('Old name')->create([
            'owner_id' => $user->id,
            'log_date' => '2026-05-15',
            'hours' => 1.0,
            'notes' => 'old',
        ]);

        $this->service->sync($user, '2026-05-15', [], [
            ['id' => $log->id, 'name' => 'New name', 'hours' => 2.5, 'notes' => 'new'],
        ]);

        $this->assertSame(1, TimeLog::count());
        $log->refresh();
        $this->assertSame('New name', $log->ad_hoc_name);
        $this->assertEqualsWithDelta(2.5, (float) $log->hours, 0.01);
        $this->assertSame('new', $log->notes);
    }

    public function test_existing_ad_hoc_row_not_resubmitted_is_deleted(): void
    {
        [$user] = $this->chain();
        $keep = TimeLog::factory()->adHoc('Keep me')->create([
            'owner_id' => $user->id, 'log_date' => '2026-05-15', 'hours' => 1.0,
        ]);
        $drop = TimeLog::factory()->adHoc('Drop me')->create([
            'owner_id' => $user->id, 'log_date' => '2026-05-15', 'hours' => 2.0,
        ]);

        // Only resubmit the "keep" row.
        $this->service->sync($user, '2026-05-15', [], [
            ['id' => $keep->id, 'name' => 'Keep me', 'hours' => 1.0, 'notes' => null],
        ]);

        $this->assertNotNull($keep->fresh());
        $this->assertNull($drop->fresh());
    }

    public function test_ad_hoc_pruning_does_not_touch_other_dates(): void
    {
        [$user] = $this->chain();
        $otherDay = TimeLog::factory()->adHoc('Yesterday')->create([
            'owner_id' => $user->id, 'log_date' => '2026-05-14', 'hours' => 1.0,
        ]);

        // Empty resubmit for today — must not delete yesterday's ad-hoc row.
        $this->service->sync($user, '2026-05-15', [], []);

        $this->assertNotNull($otherDay->fresh());
    }

    public function test_sync_handles_planned_and_ad_hoc_together_atomically(): void
    {
        [$user, $deliverable] = $this->chain();
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => '2026-05-15',
            'hours' => 1.0,
        ]);

        $this->service->sync($user, '2026-05-15', [
            $deliverable->id => ['hours' => 3.0, 'notes' => null],
        ], [
            ['id' => null, 'name' => 'OK', 'hours' => 0.5, 'notes' => null],
        ]);

        // 1 updated planned + 1 created ad-hoc.
        $this->assertSame(2, TimeLog::count());
        $planned = TimeLog::whereNotNull('deliverable_id')->first();
        $this->assertEqualsWithDelta(3.0, (float) $planned->hours, 0.01);
    }
}
