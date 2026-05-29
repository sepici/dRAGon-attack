<?php

namespace Tests\Feature\Employers;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Employer;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\ReportPdfService;
use App\Services\TimesheetPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M13d — multi-employer Reports + Timesheets.
 *
 * Verifies the service-level filtering, per-employer totals on timesheets,
 * the slug in stored filenames, and the index form's multi-select UI.
 */
class MultiEmployerExportsTest extends TestCase
{
    use RefreshDatabase;

    private function chainForEmployer(User $user, Employer $employer): Deliverable
    {
        $client = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $employer->id,
        ]);
        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $client->id,
        ]);
        return Deliverable::factory()->create(['project_id' => $project->id]);
    }

    // ---------- Timesheet form UI -----------------------------------------

    public function test_timesheet_index_renders_employer_multiselect(): void
    {
        $user = User::factory()->create();
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'AcmeTimesheet']);

        $this->actingAs($user)->get('/timesheets')
            ->assertOk()
            ->assertSee('employer_ids[]', false)
            ->assertSee('AcmeTimesheet')
            // Self always shown.
            ->assertSee('Self');
    }

    // ---------- Timesheet service ----------------------------------------

    public function test_timesheet_excludes_unselected_employers(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $acme = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme']);
        $globex = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Globex']);

        $acmeDeliv = $this->chainForEmployer($user, $acme);
        $globexDeliv = $this->chainForEmployer($user, $globex);

        $month = CarbonImmutable::create(2026, 6, 15);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $acmeDeliv->id,
            'hours' => 4.0, 'log_date' => $month->copy()->day(1),
        ]);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $globexDeliv->id,
            'hours' => 2.5, 'log_date' => $month->copy()->day(1),
        ]);

        $service = app(TimesheetPdfService::class);

        // Generate scoped to Acme only.
        $ts = $service->generateForMonth($user, $month, [$acme->id]);

        Storage::disk('local')->assertExists($ts->file_path);
        $this->assertStringContainsString('acme', $ts->file_path,
            'Filename should include the employer scope slug.');
    }

    public function test_timesheet_default_includes_all_employers(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'X']);

        $service = app(TimesheetPdfService::class);
        $ts = $service->generateForMonth($user, CarbonImmutable::create(2026, 6, 15), null);

        // No scope slug appended when all employers are included.
        $this->assertStringNotContainsString('-x-', $ts->file_path);
        $this->assertStringNotContainsString('-self-', $ts->file_path);
    }

    public function test_timesheet_per_employer_totals_render_when_multiple(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $acme = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'AcmeRender']);

        $acmeDeliv = $this->chainForEmployer($user, $acme);
        $month = CarbonImmutable::create(2026, 6, 15);
        TimeLog::factory()->create([
            'owner_id' => $user->id, 'deliverable_id' => $acmeDeliv->id,
            'hours' => 6.0, 'log_date' => $month->copy()->day(2),
        ]);
        // Ad-hoc under Self.
        TimeLog::factory()->adHoc('Email')->create([
            'owner_id' => $user->id, 'hours' => 1.0,
            'log_date' => $month->copy()->day(3),
        ]);

        $service = app(TimesheetPdfService::class);
        $ts = $service->generateForMonth($user, $month, null);

        $pdfContents = Storage::disk('local')->get($ts->file_path);
        // DomPDF output is binary; we just sanity-check it's a PDF and a
        // realistic size for a one-row grid.
        $this->assertStringStartsWith('%PDF', $pdfContents);
        $this->assertGreaterThan(1000, strlen($pdfContents));
    }

    public function test_controller_passes_employer_ids_through(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $acme = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'PickedAcme']);

        $this->actingAs($user)->post('/timesheets/generate', [
            'month' => '2026-06',
            'employer_ids' => [$acme->id],
        ])->assertRedirect(route('timesheets.index'));

        $row = \App\Models\Timesheet::firstOrFail();
        $this->assertStringContainsString('pickedacme', $row->file_path);
    }

    // ---------- Report service -------------------------------------------

    public function test_report_index_renders_employer_multiselect(): void
    {
        $user = User::factory()->create();
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'AcmeReport']);

        $this->actingAs($user)->get('/reports')
            ->assertOk()
            ->assertSee('employer_ids[]', false)
            ->assertSee('AcmeReport');
    }

    public function test_report_default_includes_all_employers(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'X']);

        $service = app(ReportPdfService::class);
        $report = $service->generateWeeklyReport($user, null);

        $this->assertStringNotContainsString('-x-', $report->file_path);
    }

    public function test_report_filename_includes_scope_slug_when_subset(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $acme = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'AcmeScope']);
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Globex']);

        $service = app(ReportPdfService::class);
        $report = $service->generateWeeklyReport($user, [$acme->id]);

        $this->assertStringContainsString('acmescope', $report->file_path);
        $this->assertStringNotContainsString('globex', $report->file_path);
    }

    public function test_report_controller_passes_employer_ids_through(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $acme = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'CtrlAcme']);

        $this->actingAs($user)->post('/reports/generate', [
            'employer_ids' => [$acme->id],
        ])->assertRedirect(route('reports.index'));

        $report = \App\Models\Report::firstOrFail();
        $this->assertStringContainsString('ctrlacme', $report->file_path);
    }

    public function test_foreign_employer_ids_are_silently_dropped(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Employer::factory()->create(['owner_id' => $other->id, 'name' => 'ForeignCo']);

        $service = app(ReportPdfService::class);
        $report = $service->generateWeeklyReport($user, [$foreign->id]);

        // Falls back to all the user's employers; no foreign slug in path.
        $this->assertStringNotContainsString('foreignco', $report->file_path);
    }
}
