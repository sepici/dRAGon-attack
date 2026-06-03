<?php

namespace Tests\Feature\Journal;

use App\Enums\PlanKind;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Employer;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M15a — the journal's "Log time on another deliverable" picker, which lets
 * the user attach a time_log to a deliverable that isn't on this week's plan.
 *
 * The submission path is the same `items[id][hours]` shape that planned items
 * use, so the controller's existing ownership check + service logic handles
 * the persistence. These tests focus on the picker data shape coming out of
 * JournalController::show().
 */
class AddOutOfPlanDeliverableTest extends TestCase
{
    use RefreshDatabase;

    public function test_picker_data_contains_users_employers_clients_projects_and_deliverables(): void
    {
        $user = User::factory()->create();
        // The User::created observer made a Self employer; add a second.
        $work = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'WorkCo']);
        $client = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $work->id,
            'legal_name' => 'Acme Ltd',
        ]);
        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $client->id,
            'name' => 'Acme Migration',
        ]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'OAuth integration',
        ]);

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($user)->get("/journal/{$date}");

        $response->assertOk();
        $picker = $response->viewData('picker');

        $this->assertIsArray($picker);
        $employerNames = collect($picker['employers'])->pluck('name')->all();
        $this->assertContains('WorkCo', $employerNames);
        $this->assertContains('Self', $employerNames); // auto-created
        // Self should come first (is_self DESC).
        $this->assertTrue((bool) $picker['employers'][0]['is_self']);

        $clientsForWork = $picker['clientsByEmployer'][$work->id];
        $this->assertSame('Acme Ltd', $clientsForWork[0]['name']);

        $projectsForClient = $picker['projectsByClient'][$client->id];
        $this->assertSame('Acme Migration', $projectsForClient[0]['name']);

        $deliverablesForProject = $picker['deliverablesByProject'][$project->id];
        $this->assertSame('OAuth integration', $deliverablesForProject[0]['name']);
        $this->assertFalse($deliverablesForProject[0]['is_complete']);
    }

    public function test_picker_does_not_leak_other_users_data(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobClient = Client::factory()->create([
            'owner_id' => $bob->id,
            'employer_id' => $bob->selfEmployer()->id,
            'legal_name' => 'Bob\'s Client',
        ]);
        $bobProject = Project::factory()->create([
            'owner_id' => $bob->id,
            'client_id' => $bobClient->id,
        ]);
        Deliverable::factory()->create(['project_id' => $bobProject->id, 'name' => 'Bob secret']);

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($alice)->get("/journal/{$date}");

        $picker = $response->viewData('picker');
        $employerIds = collect($picker['employers'])->pluck('id')->all();
        $this->assertNotContains($bob->selfEmployer()->id, $employerIds);

        // No Bob clients/projects/deliverables under any key.
        $allClientNames = collect($picker['clientsByEmployer'])->flatten(1)->pluck('name')->all();
        $this->assertNotContains("Bob's Client", $allClientNames);
    }

    public function test_picker_excludes_deliverables_already_on_this_weeks_plan(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $user->selfEmployer()->id,
        ]);
        $project = Project::factory()->create(['owner_id' => $user->id, 'client_id' => $client->id]);
        $planned = Deliverable::factory()->create(['project_id' => $project->id, 'name' => 'Planned item']);
        $unplanned = Deliverable::factory()->create(['project_id' => $project->id, 'name' => 'Unplanned item']);

        $period = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        PlanItem::factory()->create([
            'plan_period_id' => $period->id,
            'deliverable_id' => $planned->id,
        ]);

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($user)->get("/journal/{$date}");

        $names = collect($response->viewData('picker')['deliverablesByProject'][$project->id])
            ->pluck('name')->all();

        $this->assertNotContains('Planned item', $names);
        $this->assertContains('Unplanned item', $names);
    }

    public function test_picker_excludes_deliverables_already_logged_on_this_date(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $user->selfEmployer()->id,
        ]);
        $project = Project::factory()->create(['owner_id' => $user->id, 'client_id' => $client->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'name' => 'Already logged']);

        $date = CarbonImmutable::now();
        TimeLog::factory()->create([
            'owner_id' => $user->id,
            'log_date' => $date->toDateString(),
            'deliverable_id' => $deliverable->id,
            'hours' => 1.5,
        ]);

        $response = $this->actingAs($user)->get("/journal/{$date->toDateString()}");

        $names = collect($response->viewData('picker')['deliverablesByProject'][$project->id])
            ->pluck('name')->all();

        $this->assertNotContains('Already logged', $names);
    }

    public function test_picker_marks_completed_deliverables(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $user->selfEmployer()->id,
        ]);
        $project = Project::factory()->create(['owner_id' => $user->id, 'client_id' => $client->id]);
        Deliverable::factory()->create([
            'project_id' => $project->id,
            'name' => 'Shipped',
            'completed_at' => CarbonImmutable::now()->subWeek(),
        ]);

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($user)->get("/journal/{$date}");

        $row = collect($response->viewData('picker')['deliverablesByProject'][$project->id])->first();
        $this->assertSame('Shipped', $row['name']);
        $this->assertTrue($row['is_complete']);
    }

    public function test_submitting_out_of_plan_deliverable_creates_time_log(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);
        // No plan_period, no plan_item — it's strictly out of plan.

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($user)->post("/journal/{$date}", [
            'items' => [
                $deliverable->id => ['hours' => 2.0, 'notes' => 'Pulled in from M+1 work'],
            ],
        ]);

        $response->assertRedirect("/journal/{$date}");
        $this->assertSame(1, TimeLog::count());
        $log = TimeLog::first();
        $this->assertSame($deliverable->id, $log->deliverable_id);
        $this->assertEqualsWithDelta(2.0, (float) $log->hours, 0.01);
        $this->assertSame('Pulled in from M+1 work', $log->notes);
    }

    public function test_submitting_someone_elses_deliverable_still_rejected(): void
    {
        // Sanity check: M15a doesn't loosen the existing ownership guard.
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobProject = Project::factory()->create(['owner_id' => $bob->id]);
        $bobDeliverable = Deliverable::factory()->create(['project_id' => $bobProject->id]);

        $date = CarbonImmutable::now()->toDateString();
        $response = $this->actingAs($alice)
            ->from("/journal/{$date}")
            ->post("/journal/{$date}", [
                'items' => [
                    $bobDeliverable->id => ['hours' => 1.0],
                ],
            ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, TimeLog::count());
    }
}
