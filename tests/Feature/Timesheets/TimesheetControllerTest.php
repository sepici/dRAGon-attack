<?php

namespace Tests\Feature\Timesheets;

use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TimesheetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_timesheets_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/timesheets');

        $response->assertOk();
        $response->assertSeeText('Timesheets');
    }

    public function test_admin_blocked_from_timesheets(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/timesheets')->assertRedirect();
    }

    public function test_viewer_blocked_from_timesheets(): void
    {
        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->get('/timesheets')->assertRedirect();
    }

    public function test_generate_creates_a_timesheet_row(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/timesheets/generate', [
            'month' => '2026-05',
        ]);

        $response->assertRedirect(route('timesheets.index'));
        $this->assertSame(1, Timesheet::count());
        $this->assertSame('2026-05-01', Timesheet::first()->month_starts_on->toDateString());
    }

    public function test_generate_with_no_month_defaults_to_this_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/timesheets/generate');

        $ts = Timesheet::first();
        $this->assertNotNull($ts);
        $this->assertSame(now()->startOfMonth()->toDateString(), $ts->month_starts_on->toDateString());
    }

    public function test_generate_with_malformed_month_falls_back_to_this_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/timesheets/generate', ['month' => 'not-a-month']);

        $ts = Timesheet::first();
        $this->assertNotNull($ts);
        $this->assertSame(now()->startOfMonth()->toDateString(), $ts->month_starts_on->toDateString());
    }

    public function test_user_can_download_own_timesheet(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/timesheets/generate', ['month' => '2026-05']);

        $ts = Timesheet::firstOrFail();
        $response = $this->actingAs($user)->get(route('timesheets.download', $ts));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_user_cannot_download_another_users_timesheet(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->post('/timesheets/generate', ['month' => '2026-05']);
        $ts = Timesheet::firstOrFail();

        $this->actingAs($b)
            ->get(route('timesheets.download', $ts))
            ->assertForbidden();
    }

    public function test_download_returns_404_when_file_missing_on_disk(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/timesheets/generate', ['month' => '2026-05']);

        $ts = Timesheet::firstOrFail();
        Storage::disk('local')->delete($ts->file_path);

        $this->actingAs($user)
            ->get(route('timesheets.download', $ts))
            ->assertNotFound();
    }
}
