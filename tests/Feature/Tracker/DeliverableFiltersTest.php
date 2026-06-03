<?php

namespace Tests\Feature\Tracker;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Employer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M15b — the /deliverables index page gains employer / client / project
 * filters (cascading), driven by query-string params.
 *
 * Filtering rules:
 *   • project_id wins (most specific)
 *   • client_id narrows by client (and employer if also set)
 *   • employer_id narrows by employer when no client/project supplied
 *   • foreign / stale ids are silently dropped (no 4xx), the listing
 *     falls back to unfiltered for that level
 */
class DeliverableFiltersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper — set up two employers, each with their own client/project/deliverable.
     *
     * @return array{
     *   user:User,
     *   selfEmployer:Employer, workEmployer:Employer,
     *   selfClient:Client, workClient:Client,
     *   selfProject:Project, workProject:Project,
     *   selfDeliverable:Deliverable, workDeliverable:Deliverable,
     * }
     */
    private function twoEmployerWorld(): array
    {
        $user = User::factory()->create();
        $selfEmployer = $user->selfEmployer();
        $workEmployer = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'WorkCo']);

        $selfClient = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $selfEmployer->id,
            'legal_name' => 'Self Side-Project Co',
        ]);
        $workClient = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $workEmployer->id,
            'legal_name' => 'Work Client Ltd',
        ]);

        $selfProject = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $selfClient->id,
            'name' => 'Self Project',
        ]);
        $workProject = Project::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $workClient->id,
            'name' => 'Work Project',
        ]);

        $selfDeliverable = Deliverable::factory()->create([
            'project_id' => $selfProject->id,
            'name' => 'Self Deliverable',
        ]);
        $workDeliverable = Deliverable::factory()->create([
            'project_id' => $workProject->id,
            'name' => 'Work Deliverable',
        ]);

        return compact(
            'user',
            'selfEmployer', 'workEmployer',
            'selfClient', 'workClient',
            'selfProject', 'workProject',
            'selfDeliverable', 'workDeliverable',
        );
    }

    public function test_index_without_filters_returns_all_deliverables(): void
    {
        ['user' => $user] = $w = $this->twoEmployerWorld();

        $response = $this->actingAs($user)->get('/deliverables');
        $response->assertOk();

        $names = collect($response->viewData('deliverables'))->pluck('name')->all();
        $this->assertContains('Self Deliverable', $names);
        $this->assertContains('Work Deliverable', $names);
    }

    public function test_employer_filter_narrows_to_employer(): void
    {
        $w = $this->twoEmployerWorld();

        $response = $this->actingAs($w['user'])
            ->get('/deliverables?employer_id=' . $w['workEmployer']->id);
        $response->assertOk();

        $names = collect($response->viewData('deliverables'))->pluck('name')->all();
        $this->assertContains('Work Deliverable', $names);
        $this->assertNotContains('Self Deliverable', $names);

        $this->assertSame($w['workEmployer']->id, $response->viewData('filters')['employer_id']);
    }

    public function test_client_filter_narrows_to_client(): void
    {
        $w = $this->twoEmployerWorld();

        $response = $this->actingAs($w['user'])
            ->get('/deliverables?client_id=' . $w['workClient']->id);
        $response->assertOk();

        $names = collect($response->viewData('deliverables'))->pluck('name')->all();
        $this->assertContains('Work Deliverable', $names);
        $this->assertNotContains('Self Deliverable', $names);
    }

    public function test_project_filter_is_most_specific(): void
    {
        $w = $this->twoEmployerWorld();

        // Add a second deliverable to the work project to make sure project_id
        // really does narrow to one project (not just one client/employer).
        Deliverable::factory()->create([
            'project_id' => $w['workProject']->id,
            'name' => 'Second Work Deliverable',
        ]);
        // And a sibling project under the same client, so client_id alone
        // would have returned its deliverables too.
        $siblingProject = Project::factory()->create([
            'owner_id' => $w['user']->id,
            'client_id' => $w['workClient']->id,
            'name' => 'Sibling Project',
        ]);
        Deliverable::factory()->create([
            'project_id' => $siblingProject->id,
            'name' => 'Sibling Deliverable',
        ]);

        $response = $this->actingAs($w['user'])
            ->get('/deliverables?project_id=' . $w['workProject']->id);

        $names = collect($response->viewData('deliverables'))->pluck('name')->all();
        $this->assertContains('Work Deliverable', $names);
        $this->assertContains('Second Work Deliverable', $names);
        $this->assertNotContains('Sibling Deliverable', $names);
    }

    public function test_foreign_filter_id_is_silently_dropped(): void
    {
        $w = $this->twoEmployerWorld();

        // A second user with their own employer — its id shouldn't be usable
        // as a filter by the first user.
        $other = User::factory()->create();
        $otherEmployer = Employer::factory()->create(['owner_id' => $other->id]);

        $response = $this->actingAs($w['user'])
            ->get('/deliverables?employer_id=' . $otherEmployer->id);
        $response->assertOk();

        // Filter rejected → all deliverables returned, employer_id reset to null.
        $this->assertNull($response->viewData('filters')['employer_id']);
        $names = collect($response->viewData('deliverables'))->pluck('name')->all();
        $this->assertContains('Self Deliverable', $names);
        $this->assertContains('Work Deliverable', $names);
    }

    public function test_picker_data_is_present_on_index(): void
    {
        $w = $this->twoEmployerWorld();

        $response = $this->actingAs($w['user'])->get('/deliverables');
        $picker = $response->viewData('picker');

        $this->assertIsArray($picker);
        $this->assertNotEmpty($picker['employers']);
        $this->assertArrayHasKey($w['workEmployer']->id, $picker['clientsByEmployer']);
        $this->assertArrayHasKey($w['workClient']->id, $picker['projectsByClient']);
    }
}
