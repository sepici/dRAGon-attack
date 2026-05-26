<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Moscow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMilestoneRequest;
use App\Http\Requests\Api\V1\UpdateMilestoneRequest;
use App\Http\Resources\MilestoneResource;
use App\Models\Milestone;
use App\Models\TimeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * REST surface for milestones — mirror of the deliverable controller. The
 * underlying status is derived (not a column) so we eager-load the child
 * deliverables slice to keep the accessor cheap, and hydrate hours_spent
 * with a single grouped SUM rather than per-row lookups.
 */
class MilestoneController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $query = Milestone::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', auth()->id()))
            ->with([
                'project.client',
                // Sliced columns: the derivedStatus accessor only needs status.
                'deliverables:id,milestone_id,status',
            ])
            ->orderBy('project_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($projectId = $request->integer('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($moscow = $request->string('moscow')->toString()) {
            if (Moscow::tryFrom($moscow)) {
                $query->where('moscow', $moscow);
            }
        }
        if ($nameLike = trim($request->string('name_like')->toString())) {
            $query->where('name', 'like', '%' . $nameLike . '%');
        }
        if ($request->has('scope_complete')) {
            $query->where(
                'scope_complete',
                filter_var($request->input('scope_complete'), FILTER_VALIDATE_BOOLEAN),
            );
        }

        $milestones = $query->paginate();

        // Hydrate hours_spent in one grouped query keyed by milestone_id.
        $this->hydrateHoursSpent($milestones->getCollection());

        return MilestoneResource::collection($milestones);
    }

    public function show(Milestone $milestone): MilestoneResource
    {
        abort_unless(
            $milestone->project()->where('owner_id', auth()->id())->exists(),
            404,
        );
        $milestone->load(['project.client', 'deliverables:id,milestone_id,status']);
        $this->hydrateHoursSpent(collect([$milestone]));

        return new MilestoneResource($milestone);
    }

    public function store(StoreMilestoneRequest $request): JsonResponse
    {
        $milestone = Milestone::create($request->validated());
        $milestone->load(['project.client', 'deliverables:id,milestone_id,status']);
        $milestone->setAttribute('hours_spent', 0.0);

        return (new MilestoneResource($milestone))->response()->setStatusCode(201);
    }

    public function update(UpdateMilestoneRequest $request, Milestone $milestone): MilestoneResource
    {
        abort_unless(
            $milestone->project()->where('owner_id', auth()->id())->exists(),
            404,
        );
        $milestone->update($request->validated());
        $milestone->load(['project.client', 'deliverables:id,milestone_id,status']);
        $this->hydrateHoursSpent(collect([$milestone]));

        return new MilestoneResource($milestone);
    }

    public function destroy(Milestone $milestone): JsonResponse
    {
        abort_unless(
            $milestone->project()->where('owner_id', auth()->id())->exists(),
            404,
        );
        $milestone->delete();

        return response()->json(null, 204);
    }

    /**
     * Single grouped SUM that fills in `hours_spent` for every milestone in
     * the given collection — sum of every time_log on any deliverable that
     * belongs to the milestone (entire-history, no period window).
     */
    private function hydrateHoursSpent(\Illuminate\Support\Collection $milestones): void
    {
        if ($milestones->isEmpty()) {
            return;
        }
        $ids = $milestones->pluck('id');
        $sums = TimeLog::query()
            ->join('deliverables', 'deliverables.id', '=', 'time_logs.deliverable_id')
            ->whereIn('deliverables.milestone_id', $ids)
            ->selectRaw('deliverables.milestone_id as mid, SUM(time_logs.hours) as total')
            ->groupBy('deliverables.milestone_id')
            ->pluck('total', 'mid');

        $milestones->each(function (Milestone $m) use ($sums) {
            $m->setAttribute('hours_spent', (float) ($sums[$m->id] ?? 0));
        });
    }
}
