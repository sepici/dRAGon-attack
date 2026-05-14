<?php

namespace Tests\Feature\Reports;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_reports_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports');

        $response->assertOk();
        $response->assertSeeText('Reports');
    }

    public function test_user_can_generate_a_weekly_report(): void
    {
        // Use the real local disk under a fake root so tests don't pollute storage
        Storage::fake('local');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/reports/generate');

        $response->assertRedirect(route('reports.index'));

        $report = Report::where('owner_id', $user->id)->firstOrFail();
        $this->assertNotNull($report->file_path);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_user_can_download_their_own_report(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        // Generate one so we have a real file
        $this->actingAs($user)->post('/reports/generate');
        $report = Report::where('owner_id', $user->id)->firstOrFail();

        $response = $this->actingAs($user)->get(route('reports.download', $report));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_cannot_download_another_users_report(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($owner)->post('/reports/generate');
        $report = Report::where('owner_id', $owner->id)->firstOrFail();

        $response = $this->actingAs($intruder)->get(route('reports.download', $report));

        $response->assertForbidden();
    }

    public function test_download_404s_when_file_missing(): void
    {
        $user = User::factory()->create();
        // Create a Report row but no file on disk
        $report = Report::create([
            'owner_id' => $user->id,
            'week_starts_on' => now()->startOfWeek()->toDateString(),
            'generated_at' => now(),
            'file_path' => 'reports/' . $user->id . '/nonexistent.pdf',
        ]);

        $response = $this->actingAs($user)->get(route('reports.download', $report));

        $response->assertNotFound();
    }

    public function test_admin_redirected_away_from_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get('/reports');
        $response->assertRedirect(route('admin.users.index'));
    }

    public function test_viewer_redirected_away_from_reports(): void
    {
        $viewer = User::factory()->viewer()->create();
        $response = $this->actingAs($viewer)->get('/reports');
        $response->assertRedirect(route('viewer.dashboard'));
    }
}
