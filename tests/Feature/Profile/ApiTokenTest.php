<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Support\ApiAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_shows_api_tokens_section_for_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('API tokens');
        $response->assertSee('Create token');
    }

    public function test_admin_does_not_see_api_tokens_section(): void
    {
        // Admin can hit /profile but the API-tokens partial is gated behind
        // isUser(), so the form shouldn't render for them.
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertDontSee('API tokens');
    }

    public function test_user_can_create_a_token_and_sees_plaintext_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.api-tokens.store'), [
            'name' => 'Claude Desktop',
            'abilities' => [ApiAbility::TIME_LOGS_WRITE, ApiAbility::TRACKER_READ],
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('api_token_created');

        $created = session('api_token_created');
        $this->assertIsString($created['plain']);
        $this->assertStringContainsString('|', $created['plain']); // Sanctum format: "id|token"
        $this->assertSame('Claude Desktop', $created['name']);

        $token = PersonalAccessToken::firstOrFail();
        $this->assertSame($user->id, $token->tokenable_id);
        $this->assertEqualsCanonicalizing(
            [ApiAbility::TIME_LOGS_WRITE, ApiAbility::TRACKER_READ],
            $token->abilities,
        );
    }

    public function test_token_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.api-tokens.store'), [
                'abilities' => [ApiAbility::TIME_LOGS_READ],
            ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_at_least_one_ability_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.api-tokens.store'), [
                'name' => 'Empty token',
            ]);

        $response->assertSessionHasErrors('abilities');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_unknown_ability_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.api-tokens.store'), [
                'name' => 'Bad ability',
                'abilities' => ['rm-rf:everything'],
            ]);

        $response->assertSessionHasErrors('abilities.0');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_admin_cannot_create_tokens(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('profile.api-tokens.store'), [
            'name' => 'Admin sneaky',
            'abilities' => [ApiAbility::WRITE_ALL],
        ]);

        $response->assertForbidden();
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('My Agent', [ApiAbility::TIME_LOGS_WRITE]);
        $this->assertSame(1, PersonalAccessToken::count());

        $response = $this->actingAs($user)
            ->delete(route('profile.api-tokens.destroy', $token->accessToken->id));

        $response->assertRedirect(route('profile.edit'));
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $token = $owner->createToken('Owner only', [ApiAbility::READ_ALL]);

        $this->actingAs($attacker)
            ->delete(route('profile.api-tokens.destroy', $token->accessToken->id));

        // Token must still exist — attacker can't delete it through the route.
        $this->assertSame(1, PersonalAccessToken::count());
    }
}
