<?php

namespace App\Http\Controllers;

use App\Enums\PlanKind;
use App\Models\Deliverable;
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

        // Editable plan items (with their deliverable + project + client).
        // Ad-hoc items (deliverable_id NULL) appear during review (M4), not
        // here on the planning view.
        $items = $period->items()
            ->whereNotNull('deliverable_id')
            ->with(['deliverable.project.client'])
            ->get();

        // Deliverables the user owns but hasn't yet added to this period —
        // populate the "Add to plan" dropdown with these.
        $allocatedDeliverableIds = $items->pluck('deliverable_id')->all();
        $availableDeliverables = Deliverable::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->whereNotIn('id', $allocatedDeliverableIds)
            ->with('project.client')
            ->orderBy('name')
            ->get();

        return view('plans.show', [
            'period' => $period,
            'kind' => $kind,
            'items' => $items,
            'availableDeliverables' => $availableDeliverables,
            'totalAllocated' => $period->totalAllocated(),
            'capacity' => $period->capacity(),
            'overUnder' => $period->overUnder(),
        ]);
    }
}
