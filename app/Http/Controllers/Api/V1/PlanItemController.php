<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePlanItemRequest;
use App\Http\Requests\Api\V1\UpdatePlanItemRequest;
use App\Http\Resources\PlanItemResource;
use App\Models\PlanItem;
use Illuminate\Http\JsonResponse;

/**
 *   POST   /api/v1/plan-items
 *   PUT    /api/v1/plan-items/{id}
 *   DELETE /api/v1/plan-items/{id}
 *
 * No index — those are surfaced via /plans/{kind}.
 */
class PlanItemController extends Controller
{
    public function store(StorePlanItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['period_kind']); // not a column, just a resolver hint

        $item = PlanItem::create($data);
        $item->load(['deliverable.project.client', 'planPeriod']);

        return (new PlanItemResource($item))->response()->setStatusCode(201);
    }

    public function update(UpdatePlanItemRequest $request, PlanItem $planItem): PlanItemResource
    {
        $this->authorizeOwnership($planItem);
        $planItem->update($request->validated());
        $planItem->load(['deliverable.project.client', 'planPeriod']);

        return new PlanItemResource($planItem);
    }

    public function destroy(PlanItem $planItem): JsonResponse
    {
        $this->authorizeOwnership($planItem);
        $planItem->delete();

        return response()->json(null, 204);
    }

    /**
     * A plan_item belongs to a user via its parent plan_period.owner_id.
     */
    private function authorizeOwnership(PlanItem $item): void
    {
        $owns = $item->planPeriod()->where('owner_id', auth()->id())->exists();
        abort_unless($owns, 404);
    }
}
