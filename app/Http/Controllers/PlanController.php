<?php

namespace App\Http\Controllers;

use App\Enums\PlanKind;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\PlanPeriod;
use Illuminate\View\View;

/**
 * Single controller for all three plan-kind pages. Each method is a thin
 * wrapper around `show(PlanKind)` so the URLs stay nicely named:
 *
 *     GET /plans/weekly    → plans.weekly
 *     GET /plans/monthly   → plans.monthly
 *     GET /plans/quarterly → plans.quarterly
 */
class PlanController extends Controller
{
    public function weekly(): View
    {
        return $this->show(PlanKind::Weekly);
    }

    public function monthly(): View
    {
        return $this->show(PlanKind::Monthly);
    }

    public function quarterly(): View
    {
        return $this->show(PlanKind::Quarterly);
    }

    private function show(PlanKind $kind): View
    {
        $user = auth()->user();
        $period = PlanPeriod::findOrCreateCurrentFor($user, $kind);

        // Load BOTH deliverable- and milestone-type allocations. The view
        // groups by milestone (deliverable's milestone, or the allocation's
        // own milestone_id for envelope rows).
        $period->load(['items' => fn ($q) => $q->with([
            'deliverable.project.client',
            'deliverable.milestone',
            'milestone.project.client',
            'milestone.deliverables', // needed for derived status on the header row
        ])]);
        $period->loadHoursSpent();
        $items = $period->items;

        // Deliverables the user owns but hasn't added to this period yet —
        // populate the "Add to plan" dropdown with these.
        $allocatedDeliverableIds = $items->pluck('deliverable_id')->filter()->all();
        $availableDeliverables = Deliverable::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->whereNotIn('id', $allocatedDeliverableIds)
            ->with(['project.client', 'milestone'])
            ->withHoursSpent()
            ->orderBy('name')
            ->get();

        // Milestones owned by the user but not yet on this period.
        $allocatedMilestoneIds = $items->pluck('milestone_id')->filter()->all();
        $availableMilestones = Milestone::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->whereNotIn('id', $allocatedMilestoneIds)
            ->with('project.client')
            ->orderBy('name')
            ->get();

        return view('plans.show', [
            'period' => $period,
            'kind' => $kind,
            'items' => $items,
            'availableDeliverables' => $availableDeliverables,
            'availableMilestones' => $availableMilestones,
            'totalAllocated' => $period->totalAllocated(),
            'capacity' => $period->capacity(),
            'overUnder' => $period->overUnder(),
        ]);
    }
}
