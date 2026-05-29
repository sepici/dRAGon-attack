<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUser() || $user->isViewer();
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->isViewer()) {
            // Scope via project → client → employer (M13c).
            return $user->grantedEmployers()
                ->where('employer_id', $project->client?->employer_id)
                ->exists();
        }

        return $user->isUser() && $project->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isUser();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isUser() && $project->owner_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isUser() && $project->owner_id === $user->id;
    }
}
