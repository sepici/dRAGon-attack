<?php

namespace Tests\Feature\Employers;

use App\Models\Client;
use App\Models\Employer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M13b — Employer web CRUD + client form scoping.
 */
class EmployerCrudTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Index + nav ------------------------------------------------

    public function test_employers_index_shows_self_first(): void
    {
        $user = User::factory()->create();
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme', 'sort_order' => 0]);

        $response = $this->actingAs($user)->get('/employers');
        $response->assertOk();
        // Self should appear before Acme in the rendered HTML.
        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, 'Acme'),
            strpos($html, 'Self'),
            'Self employer should be listed before non-Self employers.',
        );
    }

    public function test_employers_index_lists_only_owned_rows(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Employer::factory()->create(['owner_id' => $alice->id, 'name' => 'AliceCo']);
        Employer::factory()->create(['owner_id' => $bob->id, 'name' => 'BobCo']);

        $this->actingAs($alice)->get('/employers')
            ->assertOk()
            ->assertSee('AliceCo')
            ->assertDontSee('BobCo');
    }

    public function test_employers_link_appears_in_tracker_dropdown(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Employers');
    }

    // ---------- Create / store --------------------------------------------

    public function test_user_can_add_an_employer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/employers', [
            'name' => 'Acme Co.',
        ]);

        $employer = Employer::where('name', 'Acme Co.')->firstOrFail();
        $response->assertRedirect(route('employers.show', $employer));
        $this->assertSame($user->id, $employer->owner_id);
        $this->assertFalse((bool) $employer->is_self);
    }

    public function test_store_rejects_blank_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->from('/employers/create')
            ->post('/employers', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_store_never_creates_a_second_self(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/employers', [
            'name' => 'Trying Self',
            // Even if a malicious form submits is_self, the FormRequest
            // overrides it back to false.
            'is_self' => '1',
        ])->assertRedirect();

        $this->assertSame(1, $user->employers()->where('is_self', true)->count());
    }

    // ---------- Edit / update ---------------------------------------------

    public function test_user_can_rename_non_self_employer(): void
    {
        $user = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Old']);

        $this->actingAs($user)->put("/employers/{$employer->id}", [
            'name' => 'New',
        ])->assertRedirect(route('employers.show', $employer));

        $this->assertSame('New', $employer->fresh()->name);
    }

    public function test_self_rename_is_rejected_with_form_error(): void
    {
        $user = User::factory()->create();
        $self = $user->selfEmployer();

        $this->actingAs($user)
            ->from("/employers/{$self->id}/edit")
            ->put("/employers/{$self->id}", ['name' => 'NotSelf'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Self', $self->fresh()->name);
    }

    // ---------- Destroy ---------------------------------------------------

    public function test_self_cannot_be_deleted_via_web(): void
    {
        $user = User::factory()->create();
        $self = $user->selfEmployer();

        $this->actingAs($user)
            ->delete("/employers/{$self->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('employers', ['id' => $self->id]);
    }

    public function test_empty_non_self_employer_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->delete("/employers/{$employer->id}")
            ->assertRedirect(route('employers.index'));

        $this->assertDatabaseMissing('employers', ['id' => $employer->id]);
    }

    public function test_employer_with_clients_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $user->id]);
        Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $employer->id,
        ]);

        $response = $this->actingAs($user)
            ->from(route('employers.show', $employer))
            ->delete("/employers/{$employer->id}");

        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('employers', ['id' => $employer->id]);
    }

    public function test_cannot_delete_another_users_employer(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($intruder)
            ->delete("/employers/{$employer->id}")
            ->assertForbidden();
    }

    // ---------- Client form scoping ---------------------------------------

    public function test_client_form_hides_employer_picker_when_only_self_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/clients/create');
        $response->assertOk()
            ->assertSee('name="employer_id"', false)
            // Picker isn't shown — only the hidden input.
            ->assertDontSee('— Pick employer —');
    }

    public function test_client_form_shows_employer_picker_when_multiple_exist(): void
    {
        $user = User::factory()->create();
        Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme Visible']);

        $response = $this->actingAs($user)->get('/clients/create');
        $response->assertOk()
            ->assertSee('— Pick employer —')
            ->assertSee('Acme Visible');
    }

    public function test_client_creation_with_only_self_auto_fills_employer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', [
            'legal_name' => 'Auto Self',
        ])->assertRedirect();

        $client = Client::where('legal_name', 'Auto Self')->firstOrFail();
        $this->assertSame($user->selfEmployer()->id, $client->employer_id);
    }

    public function test_client_creation_rejects_employer_from_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignEmployer = Employer::factory()->create(['owner_id' => $other->id]);

        // User has multiple employers so the picker is shown.
        Employer::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->from('/clients/create')
            ->post('/clients', [
                'employer_id' => $foreignEmployer->id,
                'legal_name' => 'Hijack',
            ])->assertSessionHasErrors('employer_id');

        $this->assertDatabaseMissing('clients', ['legal_name' => 'Hijack']);
    }

    public function test_client_show_page_displays_employer_chip(): void
    {
        $user = User::factory()->create();
        $employer = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme Visible']);
        $client = Client::factory()->create([
            'owner_id' => $user->id,
            'employer_id' => $employer->id,
        ]);

        $this->actingAs($user)->get("/clients/{$client->id}")
            ->assertOk()
            ->assertSee('Acme Visible');
    }
}
