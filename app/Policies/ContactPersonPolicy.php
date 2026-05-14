<?php

namespace App\Policies;

use App\Models\ContactPerson;
use App\Models\User;

/**
 * Contact persons inherit authorisation from their parent client.
 * (If you can edit the client, you can manage its contacts.)
 */
class ContactPersonPolicy
{
    public function view(User $user, ContactPerson $contact): bool
    {
        return $user->can('view', $contact->client);
    }

    public function create(User $user): bool
    {
        return $user->isUser();
    }

    public function update(User $user, ContactPerson $contact): bool
    {
        return $user->can('update', $contact->client);
    }

    public function delete(User $user, ContactPerson $contact): bool
    {
        return $user->can('delete', $contact->client);
    }
}
