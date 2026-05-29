<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\User;

/**
 * Milestones inherit authorisation from their parent project's owner.
 * Same pattern as DeliverablePolicy.
 */
class MilestonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUser() || $user->isViewer();
    }

    public function view(User $user, Milestone $milestone): bool
    {
        if ($user->isViewer()) {
            return $user->grantedEmployers()
                ->where('employer_id', $milestone->project?->client?->employer_id)
                ->exists();
        }
        return $user->isUser() && $milestone->project->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isUser();
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $user->isUser() && $milestone->project->owner_id === $user->id;
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $user->isUser() && $milestone->project->owner_id === $user->id;
    }
}
