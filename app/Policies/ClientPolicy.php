<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Authorise actions on a Client.
 *
 *   User    — full CRUD on clients they own (`owner_id = user.id`).
 *   Viewer  — read-only on all clients.
 *   Admin   — never reaches here; admin routes are separated by middleware.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUser() || $user->isViewer();
    }

    public function view(User $user, Client $client): bool
    {
        if ($user->isViewer()) {
            // Scoped to the viewer's granted employers (M13c).
            return $user->grantedEmployers()
                ->where('employer_id', $client->employer_id)
                ->exists();
        }

        return $user->isUser() && $client->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isUser();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->isUser() && $client->owner_id === $user->id;
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isUser() && $client->owner_id === $user->id;
    }
}
