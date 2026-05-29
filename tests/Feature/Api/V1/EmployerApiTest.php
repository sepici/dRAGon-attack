<?php

namespace Tests\Feature\Api\V1;

use App\Models\Client;
use App\Models\Employer;
use App\Models\User;
use App\Support\ApiAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M13e — REST surface for employers + client API's employer_id field.
 */
class EmployerApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUserWithAbilities(array $abilities = ['*']): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, $abilities);
        return $user;
    }

    // ---------- GET /employers ---------------------------------------------

    public function test_index_returns_self_first_then_others(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Zeta']);
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme']);

        $response = $this->getJson('/api/v1/employers');
        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        // Self pinned to the top regardless of name order.
        $this->assertSame('Self', $names[0] ?? null);
        $this->assertContains('Acme', $names);
        $this->assertContains('Zeta', $names);
    }

    public function test_index_filters_by_is_self(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'OtherCo']);

        $this->getJson('/api/v1/employers?is_self=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_self', true);

        $this->getJson('/api/v1/employers?is_self=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'OtherCo');
    }

    public function test_index_excludes_other_users_employers(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $other = User::factory()->create();
        Employer::factory()->create(['owner_id' => $other->id, 'name' => 'NotMine']);

        $this->getJson('/api/v1/employers')
            ->assertOk()
            ->assertJsonMissing(['name' => 'NotMine']);
    }

    // ---------- GET /employers/{id} ---------------------------------------

    public function test_show_includes_clients_count(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $employer = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'WithClients']);
        Client::factory()->count(2)->create([
            'owner_id' => $user->id,
            'employer_id' => $employer->id,
        ]);

        $this->getJson("/api/v1/employers/{$employer->id}")
            ->assertOk()
            ->assertJsonPath('data.clients_count', 2);
    }

    public function test_show_404s_for_other_users_employer(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $other = Employer::factory()->create();

        $this->getJson("/api/v1/employers/{$other->id}")->assertNotFound();
    }

    // ---------- POST /employers -------------------------------------------

    public function test_create_employer(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);

        $this->postJson('/api/v1/employers', [
            'name' => 'Acme Holdings',
        ])->assertStatus(201)
          ->assertJsonPath('data.name', 'Acme Holdings')
          ->assertJsonPath('data.is_self', false);

        $this->assertDatabaseHas('employers', [
            'owner_id' => $user->id,
            'name' => 'Acme Holdings',
            'is_self' => false,
        ]);
    }

    public function test_create_employer_forces_is_self_false_even_if_supplied(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);

        $this->postJson('/api/v1/employers', [
            'name' => 'TryingSelf',
            'is_self' => true,
        ])->assertStatus(201)
          ->assertJsonPath('data.is_self', false);

        // Only one Self per user.
        $this->assertSame(1, Employer::where('owner_id', $user->id)->where('is_self', true)->count());
    }

    public function test_create_requires_write_ability(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_READ]);
        $this->postJson('/api/v1/employers', ['name' => 'NoAuth'])
            ->assertForbidden();
    }

    // ---------- PUT /employers/{id} ---------------------------------------

    public function test_update_renames_non_self(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $emp = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Old']);

        $this->putJson("/api/v1/employers/{$emp->id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New');
    }

    public function test_update_rejects_renaming_self_with_422(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $self = $user->selfEmployer();

        $this->putJson("/api/v1/employers/{$self->id}", ['name' => 'NotSelf'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame('Self', $self->fresh()->name);
    }

    public function test_update_404s_for_other_user(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $other = Employer::factory()->create();

        $this->putJson("/api/v1/employers/{$other->id}", ['name' => 'Hijack'])
            ->assertNotFound();
    }

    // ---------- DELETE /employers/{id} ------------------------------------

    public function test_delete_empty_non_self_employer(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $emp = Employer::factory()->create(['owner_id' => $user->id]);

        $this->deleteJson("/api/v1/employers/{$emp->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('employers', ['id' => $emp->id]);
    }

    public function test_delete_self_returns_422(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $self = $user->selfEmployer();

        $this->deleteJson("/api/v1/employers/{$self->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.employer.0', 'cannot_delete_self');
    }

    public function test_delete_employer_with_clients_returns_422(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $emp = Employer::factory()->create(['owner_id' => $user->id]);
        Client::factory()->create(['owner_id' => $user->id, 'employer_id' => $emp->id]);

        $this->deleteJson("/api/v1/employers/{$emp->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.employer.0', 'has_clients');
    }

    // ---------- Client API: employer_id on write -------------------------

    public function test_create_client_defaults_employer_id_to_self(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'No Explicit Employer',
        ])->assertStatus(201);

        $client = Client::where('legal_name', 'No Explicit Employer')->firstOrFail();
        $this->assertSame($user->selfEmployer()->id, $client->employer_id);
    }

    public function test_create_client_accepts_explicit_employer_id(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $emp = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'CtxAcme']);

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Under Acme',
            'employer_id' => $emp->id,
        ])->assertStatus(201)
          ->assertJsonPath('data.employer_id', $emp->id);
    }

    public function test_create_client_rejects_foreign_employer_id(): void
    {
        $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $foreign = Employer::factory()->create();

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Sneaky',
            'employer_id' => $foreign->id,
        ])->assertStatus(422)
          ->assertJsonValidationErrors('employer_id');
    }

    public function test_update_client_can_move_to_different_employer(): void
    {
        $user = $this->actingAsUserWithAbilities([ApiAbility::TRACKER_WRITE]);
        $from = Employer::factory()->create(['owner_id' => $user->id]);
        $to = Employer::factory()->create(['owner_id' => $user->id]);
        $client = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $from->id,
        ]);

        $this->putJson("/api/v1/clients/{$client->id}", [
            'employer_id' => $to->id,
        ])->assertOk()
          ->assertJsonPath('data.employer_id', $to->id);
    }
}
