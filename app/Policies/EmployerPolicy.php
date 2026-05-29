<?php

namespace App\Policies;

use App\Models\Employer;
use App\Models\User;

/**
 * Authorisation on the Employer model.
 *
 *   User    — full CRUD on employers they own; Self is special-cased
 *             (cannot delete) by the model-level guard, not here.
 *   Viewer  — view only, scoped to employers granted via employer_viewers
 *             (the join behaviour lives on the controller's index query;
 *             this policy just gates per-record view).
 *   Admin   — never routes here.
 */
class EmployerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUser() || $user->isViewer();
    }

    public function view(User $user, Employer $employer): bool
    {
        if ($user->isUser()) {
            return $employer->owner_id === $user->id;
        }
        if ($user->isViewer()) {
            return $user->grantedEmployers()->where('employer_id', $employer->id)->exists();
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->isUser();
    }

    public function update(User $user, Employer $employer): bool
    {
        return $user->isUser() && $employer->owner_id === $user->id;
    }

    public function delete(User $user, Employer $employer): bool
    {
        // Owner only — and the model's deleting() guard refuses Self / non-empty
        // employers regardless of policy outcome. We refuse here too for a
        // friendlier-than-exception UI flow.
        return $user->isUser()
            && $employer->owner_id === $user->id
            && ! $employer->is_self;
    }
}
