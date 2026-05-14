<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanItemRequest;
use App\Http\Requests\UpdatePlanItemRequest;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use Illuminate\Http\RedirectResponse;

/**
 * Stores / updates / removes a single allocation row on a plan period.
 *
 *     POST   /plan-items                 plan-items.store
 *     PUT    /plan-items/{plan_item}     plan-items.update
 *     DELETE /plan-items/{plan_item}     plan-items.destroy
 *
 * The form on each plans/show page submits here with a hidden plan_period_id.
 */
class PlanItemController extends Controller
{
    public function store(StorePlanItemRequest $request): RedirectResponse
    {
        PlanItem::create($request->validated());

        return redirect()->back()->with('status', 'Added to plan.');
    }

    public function update(UpdatePlanItemRequest $request, PlanItem $planItem): RedirectResponse
    {
        $this->ensureOwnsParentPeriod($planItem);

        $planItem->update($request->validated());

        return redirect()->back()->with('status', 'Allocation updated.');
    }

    public function destroy(PlanItem $planItem): RedirectResponse
    {
        $this->ensureOwnsParentPeriod($planItem);

        $planItem->delete();

        return redirect()->back()->with('status', 'Removed from plan.');
    }

    /**
     * Block any attempt to modify a plan_item belonging to another user.
     * Cheap O(1) lookup via the period's owner_id (no policy needed for
     * this narrow case).
     */
    private function ensureOwnsParentPeriod(PlanItem $planItem): void
    {
        $period = PlanPeriod::findOrFail($planItem->plan_period_id);
        abort_unless($period->owner_id === auth()->id(), 403);
    }
}
