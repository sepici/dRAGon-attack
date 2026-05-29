<?php

namespace App\Policies;

use App\Models\Deliverable;
use App\Models\User;

/**
 * Deliverables inherit authorisation from their parent project's owner.
 */
class DeliverablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUser() || $user->isViewer();
    }

    public function view(User $user, Deliverable $deliverable): bool
    {
        if ($user->isViewer()) {
            // Scope via deliverable → project → client → employer (M13c).
            return $user->grantedEmployers()
                ->where('employer_id', $deliverable->project?->client?->employer_id)
                ->exists();
        }

        return $user->isUser() && $deliverable->project->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isUser();
    }

    public function update(User $user, Deliverable $deliverable): bool
    {
        return $user->isUser() && $deliverable->project->owner_id === $user->id;
    }

    public function delete(User $user, Deliverable $deliverable): bool
    {
        return $user->isUser() && $deliverable->project->owner_id === $user->id;
    }
}
