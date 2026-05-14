<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Client CRUD for the User role.
 *
 * Scoping: a user only ever sees their own clients (`owner_id =
 * auth()->id()`). The ClientPolicy enforces this; the index query
 * additionally filters server-side so viewers and users get sensible
 * default lists.
 */
class ClientController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Client::class, 'client');
    }

    public function index(): View
    {
        $clients = auth()->user()->isViewer()
            ? Client::with('owner')->orderBy('legal_name')->get()
            : auth()->user()->clients()->orderBy('legal_name')->get();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        $client = new Client();

        return view('clients.create', compact('client'));
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = auth()->user()->clients()->create($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Client created.');
    }

    public function show(Client $client): View
    {
        $client->load(['contactPersons' => fn ($q) => $q->orderBy('last_name')]);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', compact('client'));
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        // If there are projects attached, refuse — Restrict FK guarantees this,
        // but a friendly error is nicer than a DB exception.
        if ($client->projects()->exists()) {
            return back()->withErrors([
                'delete' => 'Cannot delete this client — it has projects attached. Remove the projects first.',
            ]);
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client deleted.');
    }
}
