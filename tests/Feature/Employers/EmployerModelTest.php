<?php

namespace Tests\Feature\Employers;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Employer;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M13a — Employer schema + Self auto-creation + ownership chain.
 *
 * Covers:
 *   • Self is auto-created on User registration
 *   • Self is never deletable / renamable
 *   • Employers with clients can't be deleted (restrict)
 *   • Client.employer_id is required + factory auto-fills from Self
 *   • TimeLog.employer_id is derived from the deliverable chain on save
 *   • Ad-hoc TimeLog requires employer_id (factory pins to Self)
 *   • Cascade: deleting a User cascades to their employers
 */
class EmployerModelTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Self auto-creation -----------------------------------------

    public function test_user_creation_auto_creates_self_employer(): void
    {
        $user = User::factory()->create();

        $this->assertSame(1, $user->employers()->count());
        $self = $user->employers()->first();
        $this->assertTrue($self->is_self);
        $this->assertSame('Self', $self->name);
    }

    public function test_self_employer_helper_is_idempotent(): void
    {
        $user = User::factory()->create();
        $a = $user->selfEmployer();
        $b = $user->selfEmployer();
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $user->employers()->where('is_self', true)->count());
    }

    public function test_user_can_have_additional_employers_alongside_self(): void
    {
        $user = User::factory()->create();
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme']);
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Globex']);

        $this->assertSame(3, $user->employers()->count(), 'Self + 2 others');
        $this->assertTrue($user->employers()->where('is_self', true)->exists());
    }

    // ---------- Self protection --------------------------------------------

    public function test_self_employer_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $self = $user->selfEmployer();

        $this->expectException(\LogicException::class);
        $self->delete();
    }

    public function test_self_employer_name_cannot_be_renamed(): void
    {
        $user = User::factory()->create();
        $self = $user->selfEmployer();

        $self->name = 'Renamed';
        $this->expectException(\LogicException::class);
        $self->save();
    }

    public function test_non_self_employer_can_be_renamed(): void
    {
        $user = User::factory()->create();
        $emp = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Old']);
        $emp->name = 'New';
        $emp->save();
        $this->assertSame('New', $emp->fresh()->name);
    }

    // ---------- Deletion guards --------------------------------------------

    public function test_employer_with_clients_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $user->id]);
        Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $employer->id,
        ]);

        $this->expectException(\LogicException::class);
        $employer->delete();
    }

    public function test_empty_non_self_employer_deletes_cleanly(): void
    {
        $user = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $user->id]);
        $employer->delete();
        $this->assertDatabaseMissing('employers', ['id' => $employer->id]);
    }

    // ---------- Client.employer_id enforcement -----------------------------

    public function test_client_without_employer_id_defaults_to_owner_self(): void
    {
        $user = User::factory()->create();
        $client = new Client([
            'owner_id' => $user->id,
            'legal_name' => 'No employer',
        ]);
        $client->save();
        $this->assertSame($user->selfEmployer()->id, $client->fresh()->employer_id);
    }

    public function test_client_factory_defaults_to_owner_self_employer(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['owner_id' => $user->id]);
        $this->assertSame($user->selfEmployer()->id, $client->employer_id);
    }

    public function test_client_belongs_to_explicit_employer_when_set(): void
    {
        $user = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme']);
        $client = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $employer->id,
        ]);
        $this->assertSame($employer->id, $client->fresh()->employer_id);
    }

    // ---------- TimeLog.employer_id derivation -----------------------------

    public function test_deliverable_linked_time_log_derives_employer_from_chain(): void
    {
        $user = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme']);
        $client = Client::factory()->create([
            'owner_id' => $user->id, 'employer_id' => $employer->id,
        ]);
        $project = Project::factory()->create([
            'owner_id' => $user->id, 'client_id' => $client->id,
        ]);
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id]);

        // Don't set employer_id — observer should derive it.
        $log = TimeLog::factory()->create([
            'owner_id' => $user->id,
            'deliverable_id' => $deliverable->id,
        ]);

        $this->assertSame($employer->id, $log->fresh()->employer_id);
    }

    public function test_ad_hoc_time_log_factory_pins_employer_to_self(): void
    {
        $user = User::factory()->create();
        $log = TimeLog::factory()
            ->adHoc('Email sweep')
            ->create(['owner_id' => $user->id]);

        $this->assertSame($user->selfEmployer()->id, $log->fresh()->employer_id);
        $this->assertNull($log->deliverable_id);
        $this->assertSame('Email sweep', $log->ad_hoc_name);
    }

    public function test_ad_hoc_time_log_without_employer_id_defaults_to_owner_self(): void
    {
        $user = User::factory()->create();
        $log = new TimeLog([
            'owner_id' => $user->id,
            'log_date' => '2026-06-01',
            'hours' => 2.0,
            'ad_hoc_name' => 'Unscoped',
        ]);
        $log->save();
        $this->assertSame($user->selfEmployer()->id, $log->fresh()->employer_id);
    }

    // ---------- Cascades ----------------------------------------------------

    public function test_deleting_user_cascades_to_their_employers(): void
    {
        $user = User::factory()->create();
        $extra = Employer::factory()->create(['owner_id' => $user->id]);
        $this->assertSame(2, $user->employers()->count());

        $user->delete();

        $this->assertDatabaseMissing('employers', ['id' => $extra->id]);
        $this->assertDatabaseMissing('employers', ['owner_id' => $user->id]);
    }
}
