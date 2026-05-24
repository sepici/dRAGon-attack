<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDeliverableRequest;
use App\Http\Requests\Api\V1\UpdateDeliverableRequest;
use App\Http\Resources\DeliverableResource;
use App\Models\Deliverable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DeliverableController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $query = Deliverable::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', auth()->id()))
            ->with(['project.client'])
            ->withHoursSpent()
            ->orderBy('deadline')
            ->orderBy('name');

        if ($projectId = $request->integer('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($status = $request->string('status')->toString()) {
            if (Status::tryFrom($status)) {
                $query->where('status', $status);
            }
        }
        if ($moscow = $request->string('moscow')->toString()) {
            if (Moscow::tryFrom($moscow)) {
                $query->where('moscow', $moscow);
            }
        }
        if ($nameLike = trim($request->string('name_like')->toString())) {
            $query->where('name', 'like', '%' . $nameLike . '%');
        }
        if ($request->has('completed')) {
            $completed = filter_var($request->input('completed'), FILTER_VALIDATE_BOOLEAN);
            $completed
                ? $query->whereNotNull('completed_at')
                : $query->whereNull('completed_at');
        }

        return DeliverableResource::collection($query->paginate());
    }

    public function show(Deliverable $deliverable): DeliverableResource
    {
        abort_unless(
            $deliverable->project()->where('owner_id', auth()->id())->exists(),
            404,
        );
        $deliverable->load(['project.client']);
        $deliverable->setAttribute(
            'hours_spent',
            (float) $deliverable->timeLogs()->sum('hours'),
        );

        return new DeliverableResource($deliverable);
    }

    public function store(StoreDeliverableRequest $request): JsonResponse
    {
        $deliverable = Deliverable::create($request->validated());
        $deliverable->load(['project.client']);
        $deliverable->setAttribute('hours_spent', 0.0);

        return (new DeliverableResource($deliverable))->response()->setStatusCode(201);
    }

    public function update(UpdateDeliverableRequest $request, Deliverable $deliverable): DeliverableResource
    {
        abort_unless(
            $deliverable->project()->where('owner_id', auth()->id())->exists(),
            404,
        );
        $deliverable->update($request->validated());
        $deliverable->load(['project.client']);
        $deliverable->setAttribute(
            'hours_spent',
            (float) $deliverable->timeLogs()->sum('hours'),
        );

        return new DeliverableResource($deliverable);
    }

    public function destroy(Deliverable $deliverable): JsonResponse
    {
        abort_unless(
            $deliverable->project()->where('owner_id', auth()->id())->exists(),
            404,
        );
        $deliverable->delete();

        return response()->json(null, 204);
    }
}
