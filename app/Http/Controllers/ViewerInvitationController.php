<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Employer;
use App\Models\User;
use App\Models\ViewerInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * The "Invite this employer to view your work" flow.
 *
 *   Inviter side (auth:user middleware applied at the route group):
 *     GET    /invitations               → list outstanding + accepted invites
 *     GET    /invitations/create        → form (pre-fills employer when ?employer=)
 *     POST   /invitations               → create + show magic link
 *     DELETE /invitations/{invitation}  → revoke a pending invite
 *
 *   Recipient side (public — token-gated):
 *     GET    /viewer-invitations/{token}        → accept form
 *     POST   /viewer-invitations/{token}/accept → set password + create grants
 */
class ViewerInvitationController extends Controller
{
    // ---------- Inviter-facing -------------------------------------------

    public function index(): View
    {
        $invitations = ViewerInvitation::query()
            ->where('inviter_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('invitations.index', compact('invitations'));
    }

    public function create(Request $request): View
    {
        $employers = auth()->user()
            ->employers()
            ->orderByDesc('is_self')->orderBy('sort_order')->orderBy('name')
            ->get();

        // ?employer=ID pre-selects (the "Invite to view" button on the
        // Employer show page passes this).
        $preselected = $request->integer('employer');

        return view('invitations.create', compact('employers', 'preselected'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:180'],
            'name' => ['nullable', 'string', 'max:200'],
            'employer_ids' => ['required', 'array', 'min:1'],
            'employer_ids.*' => [
                'integer',
                function ($attribute, $value, $fail) use ($user) {
                    $owns = Employer::where('id', $value)
                        ->where('owner_id', $user->id)
                        ->exists();
                    if (! $owns) {
                        $fail('You can only invite a viewer to employers you own.');
                    }
                },
            ],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $invitation = ViewerInvitation::create([
            'inviter_id' => $user->id,
            'email' => strtolower(trim($validated['email'])),
            'name' => $validated['name'] ?? null,
            'employer_ids' => array_values(array_unique(array_map('intval', $validated['employer_ids']))),
            'message' => $validated['message'] ?? null,
            // 14-day default window. Tweak as needed later.
            'expires_at' => Carbon::now()->addDays(14),
        ]);

        return redirect()
            ->route('invitations.index')
            ->with('status', 'Invitation created. Copy the magic link below to share it.')
            ->with('invitation_id', $invitation->id);
    }

    public function destroy(ViewerInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->inviter_id === auth()->id(), 403);
        // Block deletion of accepted invites — those represent live grants
        // that should be revoked via the employer's "Manage viewers" page
        // (or removing the employer_viewers row directly). Pending ones
        // are safe to scrap.
        if ($invitation->isAccepted()) {
            return back()->withErrors([
                'delete' => 'This invitation has already been accepted; revoke access from the viewer side instead.',
            ]);
        }
        $invitation->delete();

        return redirect()
            ->route('invitations.index')
            ->with('status', 'Invitation revoked.');
    }

    // ---------- Recipient-facing (public, token-gated) -------------------

    public function showAccept(string $token): View|RedirectResponse
    {
        $invitation = ViewerInvitation::query()
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->isAccepted()) {
            return redirect()->route('login')
                ->with('status', 'This invitation has already been accepted; please log in.');
        }
        if ($invitation->isExpired()) {
            abort(410, 'This invitation has expired.');
        }

        $employers = Employer::whereIn('id', $invitation->employer_ids)
            ->with('owner:id,name,email')
            ->get();

        return view('invitations.accept', compact('invitation', 'employers'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = ViewerInvitation::query()
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->isAccepted()) {
            return redirect()->route('login')
                ->with('status', 'This invitation has already been accepted; please log in.');
        }
        if ($invitation->isExpired()) {
            abort(410, 'This invitation has expired.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Reuse an existing viewer account with the same email if one exists;
        // otherwise create one. We never silently bind to a user / admin
        // account — emails on those roles ask the inviter to use a different
        // recipient address.
        $viewer = User::where('email', $invitation->email)->first();
        if ($viewer && $viewer->role !== UserRole::Viewer) {
            return back()->withErrors([
                'email' => 'That email is already used by a non-viewer account. Ask the inviter to use a different address.',
            ]);
        }
        if (! $viewer) {
            $viewer = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'], // hashed via the User cast
                'role' => UserRole::Viewer->value,
            ]);
        } else {
            // Existing viewer accepting another invitation — update password
            // only if they chose to.
            $viewer->update(['name' => $validated['name'], 'password' => Hash::make($validated['password'])]);
        }

        // Materialise the grants. syncWithoutDetaching keeps any grants the
        // viewer already had from prior invitations.
        $viewer->grantedEmployers()->syncWithoutDetaching($invitation->employer_ids);

        $invitation->update([
            'viewer_id' => $viewer->id,
            'accepted_at' => Carbon::now(),
        ]);

        return redirect()->route('login')
            ->with('status', 'Account created. Please log in.');
    }
}
