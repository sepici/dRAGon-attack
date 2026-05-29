<?php

namespace Tests\Feature\Employers;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Employer;
use App\Models\Project;
use App\Models\User;
use App\Models\ViewerInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * M13c — viewer invitation lifecycle + post-acceptance scoping.
 */
class ViewerInvitationTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Inviter side ----------------------------------------------

    public function test_user_can_create_an_invitation(): void
    {
        $user = User::factory()->create();
        $acme = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme']);

        $response = $this->actingAs($user)->post('/invitations', [
            'email' => 'Viewer@Example.com',
            'name' => 'Viewer V',
            'employer_ids' => [$acme->id, $user->selfEmployer()->id],
            'message' => 'Weekly status.',
        ]);

        $response->assertRedirect(route('invitations.index'));
        $inv = ViewerInvitation::firstOrFail();
        $this->assertSame($user->id, $inv->inviter_id);
        $this->assertSame('viewer@example.com', $inv->email);
        $this->assertEqualsCanonicalizing(
            [$acme->id, $user->selfEmployer()->id],
            $inv->employer_ids,
        );
        $this->assertNotEmpty($inv->token);
        $this->assertNotNull($inv->expires_at);
    }

    public function test_invitation_rejects_employer_owned_by_someone_else(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Employer::factory()->create(['owner_id' => $other->id]);

        $this->actingAs($user)
            ->from('/invitations/create')
            ->post('/invitations', [
                'email' => 'v@example.com',
                'employer_ids' => [$foreign->id],
            ])->assertSessionHasErrors('employer_ids.0');
    }

    public function test_index_lists_only_own_invitations(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        ViewerInvitation::create([
            'inviter_id' => $alice->id, 'email' => 'a@example.com',
            'employer_ids' => [$alice->selfEmployer()->id],
        ]);
        ViewerInvitation::create([
            'inviter_id' => $bob->id, 'email' => 'b@example.com',
            'employer_ids' => [$bob->selfEmployer()->id],
        ]);

        $this->actingAs($alice)->get('/invitations')
            ->assertOk()
            ->assertSee('a@example.com')
            ->assertDontSee('b@example.com');
    }

    public function test_pending_invitation_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $inv = ViewerInvitation::create([
            'inviter_id' => $user->id, 'email' => 'v@example.com',
            'employer_ids' => [$user->selfEmployer()->id],
        ]);

        $this->actingAs($user)->delete("/invitations/{$inv->id}")
            ->assertRedirect(route('invitations.index'));

        $this->assertDatabaseMissing('viewer_invitations', ['id' => $inv->id]);
    }

    public function test_cannot_revoke_another_users_invitation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $inv = ViewerInvitation::create([
            'inviter_id' => $owner->id, 'email' => 'v@example.com',
            'employer_ids' => [$owner->selfEmployer()->id],
        ]);

        $this->actingAs($intruder)->delete("/invitations/{$inv->id}")
            ->assertForbidden();
    }

    // ---------- Recipient side --------------------------------------------

    public function test_recipient_can_open_the_accept_page(): void
    {
        $user = User::factory()->create();
        $emp = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'AcmeShared']);
        $inv = ViewerInvitation::create([
            'inviter_id' => $user->id, 'email' => 'v@example.com',
            'employer_ids' => [$emp->id],
        ]);

        $this->get("/viewer-invitations/{$inv->token}")
            ->assertOk()
            ->assertSee('AcmeShared')
            ->assertSee('v@example.com');
    }

    public function test_accept_creates_viewer_and_grants(): void
    {
        $user = User::factory()->create();
        $emp = Employer::factory()->create(['owner_id' => $user->id, 'name' => 'Granted']);
        $self = $user->selfEmployer();
        $inv = ViewerInvitation::create([
            'inviter_id' => $user->id, 'email' => 'newviewer@example.com',
            'employer_ids' => [$emp->id, $self->id],
        ]);

        $response = $this->post("/viewer-invitations/{$inv->token}/accept", [
            'name' => 'New Viewer',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);
        $response->assertRedirect(route('login'));

        $viewer = User::where('email', 'newviewer@example.com')->firstOrFail();
        $this->assertTrue($viewer->isViewer());
        $this->assertSame('New Viewer', $viewer->name);
        $this->assertTrue(Hash::check('secret12345', $viewer->password));

        $granted = $viewer->grantedEmployers()->pluck('employer_id')->all();
        $this->assertEqualsCanonicalizing([$emp->id, $self->id], $granted);

        $inv->refresh();
        $this->assertNotNull($inv->accepted_at);
        $this->assertSame($viewer->id, $inv->viewer_id);
    }

    public function test_accept_rejects_token_after_acceptance(): void
    {
        $user = User::factory()->create();
        $emp = Employer::factory()->create(['owner_id' => $user->id]);
        $inv = ViewerInvitation::create([
            'inviter_id' => $user->id, 'email' => 'twice@example.com',
            'employer_ids' => [$emp->id],
            'accepted_at' => now(),
        ]);

        $this->get("/viewer-invitations/{$inv->token}")
            ->assertRedirect(route('login'));
    }

    public function test_accept_404s_on_unknown_token(): void
    {
        $this->get('/viewer-invitations/notarealtoken')->assertNotFound();
    }

    public function test_accept_410s_when_expired(): void
    {
        $user = User::factory()->create();
        $emp = Employer::factory()->create(['owner_id' => $user->id]);
        $inv = ViewerInvitation::create([
            'inviter_id' => $user->id, 'email' => 'late@example.com',
            'employer_ids' => [$emp->id],
            'expires_at' => now()->subDays(1),
        ]);

        $response = $this->get("/viewer-invitations/{$inv->token}");
        $response->assertStatus(410);
    }

    // ---------- Policy: viewer scoping ------------------------------------

    public function test_viewer_can_only_see_clients_under_granted_employers(): void
    {
        $owner = User::factory()->create();
        $grantedEmployer = Employer::factory()->create(['owner_id' => $owner->id, 'name' => 'Granted']);
        $hiddenEmployer = Employer::factory()->create(['owner_id' => $owner->id, 'name' => 'Hidden']);

        $visibleClient = Client::factory()->create([
            'owner_id' => $owner->id,
            'employer_id' => $grantedEmployer->id,
            'legal_name' => 'Visible Client',
        ]);
        $hiddenClient = Client::factory()->create([
            'owner_id' => $owner->id,
            'employer_id' => $hiddenEmployer->id,
            'legal_name' => 'Hidden Client',
        ]);

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $viewer->grantedEmployers()->attach($grantedEmployer->id);

        $this->assertTrue($viewer->can('view', $visibleClient));
        $this->assertFalse($viewer->can('view', $hiddenClient));
    }

    public function test_viewer_can_only_see_deliverables_under_granted_employers(): void
    {
        $owner = User::factory()->create();
        $grantedEmp = Employer::factory()->create(['owner_id' => $owner->id]);
        $hiddenEmp = Employer::factory()->create(['owner_id' => $owner->id]);

        $clientA = Client::factory()->create(['owner_id' => $owner->id, 'employer_id' => $grantedEmp->id]);
        $clientB = Client::factory()->create(['owner_id' => $owner->id, 'employer_id' => $hiddenEmp->id]);
        $projA = Project::factory()->create(['owner_id' => $owner->id, 'client_id' => $clientA->id]);
        $projB = Project::factory()->create(['owner_id' => $owner->id, 'client_id' => $clientB->id]);
        $deliverableA = Deliverable::factory()->create(['project_id' => $projA->id]);
        $deliverableB = Deliverable::factory()->create(['project_id' => $projB->id]);

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $viewer->grantedEmployers()->attach($grantedEmp->id);

        $this->assertTrue($viewer->can('view', $deliverableA));
        $this->assertFalse($viewer->can('view', $deliverableB));
    }

    public function test_viewer_dashboard_lists_granted_employers_only(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $aliceEmp = Employer::factory()->create(['owner_id' => $alice->id, 'name' => 'AliceCo']);

        $bob = User::factory()->create(['name' => 'Bob']);
        $bobEmp = Employer::factory()->create(['owner_id' => $bob->id, 'name' => 'BobCo']);

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $viewer->grantedEmployers()->attach($aliceEmp->id);

        $this->actingAs($viewer)->get('/viewer/dashboard')
            ->assertOk()
            ->assertSee('AliceCo')
            ->assertDontSee('BobCo');
    }

    // ---------- Inviter dropdown link visibility --------------------------

    public function test_user_dropdown_includes_viewer_invitations_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Viewer invitations');
    }
}
