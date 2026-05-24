<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Support\ApiAbility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * User-facing CRUD for personal-access tokens on /profile.
 *
 *   POST   /profile/api-tokens/{tokenId}/revoke   revoke an existing token
 *   POST   /profile/api-tokens                    create a new token
 *
 * Token creation flashes the PLAINTEXT token back to the profile page in
 * the session — that's the user's only chance to see it. After they
 * navigate away it's lost forever (same model as GitHub PATs).
 */
class ApiTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(ApiAbility::values())],
        ]);

        // Only user-role accounts can issue tokens. Admins / viewers shouldn't
        // be able to bypass the UI by hand-crafting a POST.
        abort_unless($request->user()->isUser(), 403);

        $token = $request->user()->createToken(
            $data['name'],
            $data['abilities'],
        );

        // Flash the plaintext token + the just-created id so the partial
        // can display it once and let the user copy it.
        return redirect()
            ->route('profile.edit')
            ->with('api_token_created', [
                'plain' => $token->plainTextToken,
                'id' => $token->accessToken->id,
                'name' => $data['name'],
            ]);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        // Sanctum stores all PATs in personal_access_tokens with a polymorphic
        // tokenable_id — scoping by tokens() makes sure we only delete the
        // current user's own tokens, never someone else's.
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'api-token-revoked');
    }
}
