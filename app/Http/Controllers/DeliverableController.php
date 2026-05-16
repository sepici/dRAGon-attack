<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliverableRequest;
use App\Http\Requests\UpdateDeliverableRequest;
use App\Models\Deliverable;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The Deliverables tab — the spreadsheet's master row list. All of a user's
 * deliverables across all projects in one filterable table.
 *
 * Optional ?project={id} query param prefills the form on /deliverables/create
 * so the "Add deliverable" button on a project page lands on the right project.
 */
class DeliverableController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Deliverable::class, 'deliverable');
    }

    public function index(): View
    {
        $user = auth()->user();

        $query = Deliverable::with(['project.client'])
            ->withHoursSpent()
            ->orderBy('deadline')
            ->orderBy('name');

        if (! $user->isViewer()) {
            $query->whereHas('project', fn ($q) => $q->where('owner_id', $user->id));
        }

        $deliverables = $query->get();

        return view('deliverables.index', compact('deliverables'));
    }

    public function create(): View
    {
        $deliverable = new Deliverable([
            'status' => 'R',
            'target_hours' => 0,
            'project_id' => request()->integer('project'), // prefill from ?project=
        ]);
        $projects = $this->projectsWithContacts();

        return view('deliverables.create', compact('deliverable', 'projects'));
    }

    public function store(StoreDeliverableRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $contactIds = $data['contact_ids'] ?? [];
        unset($data['contact_ids']);

        $deliverable = Deliverable::create($data);
        $deliverable->contactPersons()->sync($contactIds);

        return redirect()
            ->route('deliverables.show', $deliverable)
            ->with('status', 'Deliverable created.');
    }

    public function show(Deliverable $deliverable): View
    {
        $deliverable->load(['project.client', 'contactPersons']);
        // Derived hours_spent for this single row.
        $deliverable->setAttribute('hours_spent', (float) $deliverable->timeLogs()->sum('hours'));

        return view('deliverables.show', compact('deliverable'));
    }

    public function edit(Deliverable $deliverable): View
    {
        $deliverable->load('contactPersons');
        $projects = $this->projectsWithContacts();

        return view('deliverables.edit', compact('deliverable', 'projects'));
    }

    public function update(UpdateDeliverableRequest $request, Deliverable $deliverable): RedirectResponse
    {
        $data = $request->validated();
        $contactIds = $data['contact_ids'] ?? [];
        unset($data['contact_ids']);

        $deliverable->update($data);
        $deliverable->contactPersons()->sync($contactIds);

        return redirect()
            ->route('deliverables.show', $deliverable)
            ->with('status', 'Deliverable updated.');
    }

    public function destroy(Deliverable $deliverable): RedirectResponse
    {
        $deliverable->delete();

        return redirect()
            ->route('deliverables.index')
            ->with('status', 'Deliverable deleted.');
    }

    /**
     * Helper — the auth user's projects, each with its client's contacts
     * eager-loaded, for the form's project + contact selectors.
     */
    private function projectsWithContacts()
    {
        return auth()->user()
            ->projects()
            ->with([
                'client',
                'client.contactPersons' => fn ($q) => $q->orderBy('last_name'),
            ])
            ->orderBy('name')
            ->get();
    }
}
