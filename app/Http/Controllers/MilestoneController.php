<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\UpdateMilestoneRequest;
use App\Models\Milestone;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Milestones list / create / edit / show.
 *
 * Optional ?project={id} on /create prefills the project selector, so the
 * "Add milestone" button on a project show page lands on the right project.
 */
class MilestoneController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Milestone::class, 'milestone');
    }

    public function index(): View
    {
        $user = auth()->user();

        $query = Milestone::with(['project.client', 'deliverables'])
            ->orderBy('project_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $user->isViewer()) {
            $query->whereHas('project', fn ($q) => $q->where('owner_id', $user->id));
        }

        $milestones = $query->get();

        return view('milestones.index', compact('milestones'));
    }

    public function create(): View
    {
        $milestone = new Milestone([
            'project_id' => request()->integer('project'),
            'scope_complete' => false,
        ]);
        $projects = $this->ownersProjects();

        return view('milestones.create', compact('milestone', 'projects'));
    }

    public function store(StoreMilestoneRequest $request): RedirectResponse
    {
        $milestone = Milestone::create($request->validated());

        return redirect()
            ->route('milestones.show', $milestone)
            ->with('status', 'Milestone created.');
    }

    public function show(Milestone $milestone): View
    {
        $milestone->load(['project.client', 'deliverables' => fn ($q) => $q->orderBy('deadline')->orderBy('name')]);

        return view('milestones.show', compact('milestone'));
    }

    public function edit(Milestone $milestone): View
    {
        $projects = $this->ownersProjects();

        return view('milestones.edit', compact('milestone', 'projects'));
    }

    public function update(UpdateMilestoneRequest $request, Milestone $milestone): RedirectResponse
    {
        $milestone->update($request->validated());

        return redirect()
            ->route('milestones.show', $milestone)
            ->with('status', 'Milestone updated.');
    }

    public function destroy(Milestone $milestone): RedirectResponse
    {
        $milestone->delete();

        return redirect()
            ->route('milestones.index')
            ->with('status', 'Milestone deleted.');
    }

    private function ownersProjects()
    {
        return auth()->user()
            ->projects()
            ->with('client')
            ->orderBy('name')
            ->get();
    }
}
