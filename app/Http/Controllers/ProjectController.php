<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Project CRUD for the User role.
 *
 * Each project belongs to a Client. The "responsible contact" must be one
 * of that client's contact persons — enforced by validation in
 * StoreProjectRequest / UpdateProjectRequest.
 */
class ProjectController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Project::class, 'project');
    }

    public function index(): View
    {
        $user = auth()->user();

        // Eager-load deliverables so the computed Project::status accessor
        // doesn't trigger N+1 queries when rendering chips.
        $with = ['client', 'deliverables:id,project_id,status'];
        if ($user->isViewer()) {
            $with[] = 'owner';
        }

        $query = Project::with($with)->orderBy('name');

        $projects = $user->isViewer()
            ? $query->get()
            : $query->where('owner_id', $user->id)->get();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $project = new Project();
        $clients = $this->clientsWithContacts();

        return view('projects.create', compact('project', 'clients'));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = auth()->user()->projects()->create($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project created.');
    }

    public function show(Project $project): View
    {
        $project->load([
            'client',
            'responsibleContact',
            'milestones' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'milestones.deliverables',
            'deliverables' => fn ($q) => $q->orderBy('deadline')->orderBy('name'),
        ]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        $clients = $this->clientsWithContacts();

        return view('projects.edit', compact('project', 'clients'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete(); // cascades to deliverables

        return redirect()
            ->route('projects.index')
            ->with('status', 'Project deleted.');
    }

    /**
     * Helper — returns the auth user's clients, each with its contact persons
     * eager-loaded, for the form's client/contact selectors.
     */
    private function clientsWithContacts()
    {
        return auth()->user()
            ->clients()
            ->with(['contactPersons' => fn ($q) => $q->orderBy('last_name')])
            ->orderBy('legal_name')
            ->get();
    }
}
