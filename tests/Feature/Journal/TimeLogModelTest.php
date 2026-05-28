<?php

namespace Tests\Feature\Journal;

use App\Models\Deliverable;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the TimeLog schema + model wiring landed in M8a.
 * The bigger journal-flow tests live in M8g; this file just pins the
 * shape of the data layer.
 */
class TimeLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliverable_linked_log_persists_with_expected_fields(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        $log = TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'log_date' => '2026-05-15',
            'hours' => 2.5,
            'notes' => 'Pair-programmed the OAuth flow.',
        ]);

        $this->assertFalse($log->isAdHoc());
        $this->assertSame($deliverable->id, $log->deliverable->id);
        $this->assertSame($user->id, $log->owner->id);
        $this->assertSame('2026-05-15', $log->log_date->toDateString());
        $this->assertEqualsWithDelta(2.5, (float) $log->hours, 0.01);
        $this->assertEqualsWithDelta(0.3125, $log->days, 0.0001); // 2.5 / 8
    }

    public function test_ad_hoc_log_has_no_deliverable_but_has_a_name(): void
    {
        $user = User::factory()->create();

        $log = TimeLog::factory()->adHoc('Cloudways nginx restart')->create([
            'owner_id' => $user->id,
            'log_date' => '2026-05-15',
            'hours' => 0.5,
        ]);

        $this->assertTrue($log->isAdHoc());
        $this->assertNull($log->deliverable_id);
        $this->assertSame('Cloudways nginx restart', $log->ad_hoc_name);
        $this->assertSame('Cloudways nginx restart', $log->displayName());
    }

    public function test_for_owner_scope_filters_to_a_users_logs(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        TimeLog::factory()->adHoc('A1')->create(['owner_id' => $a->id]);
        TimeLog::factory()->adHoc('A2')->create(['owner_id' => $a->id]);
        TimeLog::factory()->adHoc('B1')->create(['owner_id' => $b->id]);

        $this->assertSame(2, TimeLog::forOwner($a)->count());
        $this->assertSame(1, TimeLog::forOwner($b)->count());
    }

    public function test_for_date_scope_filters_to_a_single_day(): void
    {
        $user = User::factory()->create();
        TimeLog::factory()->adHoc('y')->create(['owner_id' => $user->id, 'log_date' => '2026-05-14']);
        TimeLog::factory()->adHoc('t1')->create(['owner_id' => $user->id, 'log_date' => '2026-05-15']);
        TimeLog::factory()->adHoc('t2')->create(['owner_id' => $user->id, 'log_date' => '2026-05-15']);

        $this->assertSame(2, TimeLog::forOwner($user)->forDate('2026-05-15')->count());
        $this->assertSame(2, TimeLog::forOwner($user)
            ->forDate(CarbonImmutable::parse('2026-05-15'))
            ->count());
    }

    public function test_in_range_scope_is_inclusive_on_both_ends(): void
    {
        $user = User::factory()->create();
        foreach (['2026-05-11', '2026-05-15', '2026-05-17', '2026-05-18'] as $d) {
            TimeLog::factory()->adHoc("d-{$d}")->create([
                'owner_id' => $user->id, 'log_date' => $d,
            ]);
        }

        // Week of 11–17 May 2026.
        $this->assertSame(3, TimeLog::forOwner($user)
            ->inRange('2026-05-11', '2026-05-17')
            ->count());
    }

    public function test_deliverable_has_many_time_logs(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        TimeLog::factory()->count(3)->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
        ]);

        $this->assertSame(3, $deliverable->timeLogs()->count());
    }

    public function test_deleting_owner_cascades_to_their_logs(): void
    {
        $user = User::factory()->create();
        TimeLog::factory()->adHoc('x')->create(['owner_id' => $user->id]);
        $this->assertSame(1, TimeLog::count());

        $user->delete();
        $this->assertSame(0, TimeLog::count());
    }

    public function test_deleting_deliverable_cascades_to_its_logs(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
        ]);

        $this->assertSame(1, TimeLog::count());
        $deliverable->delete();
        $this->assertSame(0, TimeLog::count());
    }
}
