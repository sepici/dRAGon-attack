<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactPersonRequest;
use App\Http\Requests\UpdateContactPersonRequest;
use App\Models\Client;
use App\Models\ContactPerson;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Contact persons live under a parent client. Routes are nested:
 *     GET    /clients/{client}/contacts/create
 *     POST   /clients/{client}/contacts
 *     GET    /clients/{client}/contacts/{contact}/edit
 *     PUT    /clients/{client}/contacts/{contact}
 *     DELETE /clients/{client}/contacts/{contact}
 *
 * The contact's `client_id` is set from the route, not the form, so a user
 * can't manually attach a contact to someone else's client.
 */
class ContactPersonController extends Controller
{
    public function create(Client $client): View
    {
        $this->authorize('update', $client);

        $contact = new ContactPerson(['client_id' => $client->id]);

        return view('clients.contacts.create', compact('client', 'contact'));
    }

    public function store(StoreContactPersonRequest $request, Client $client): RedirectResponse
    {
        $client->contactPersons()->create($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Contact added.');
    }

    public function edit(Client $client, ContactPerson $contact): View
    {
        $this->ensureBelongsToClient($contact, $client);
        $this->authorize('update', $contact);

        return view('clients.contacts.edit', compact('client', 'contact'));
    }

    public function update(UpdateContactPersonRequest $request, Client $client, ContactPerson $contact): RedirectResponse
    {
        $this->ensureBelongsToClient($contact, $client);

        $contact->update($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Contact updated.');
    }

    public function destroy(Client $client, ContactPerson $contact): RedirectResponse
    {
        $this->ensureBelongsToClient($contact, $client);
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Contact removed.');
    }

    /**
     * Defensive check: the nested {contact} must belong to {client}.
     * Otherwise someone could URL-tamper and PUT a contact under the wrong
     * parent (even if the policy passes on the contact itself).
     */
    private function ensureBelongsToClient(ContactPerson $contact, Client $client): void
    {
        abort_unless($contact->client_id === $client->id, 404);
    }
}
