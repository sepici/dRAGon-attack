<?php

namespace App\Http\Controllers;

use App\Enums\PlanKind;
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

    /**
     * Render the plan page for the current period of the given kind.
     * Auto-creates the period on first visit (idempotent).
     */
    private function show(PlanKind $kind): View
    {
        $user = auth()->user();
        $period = PlanPeriod::findOrCreateCurrentFor($user, $kind);

        // Items with their deliverable + project + client preloaded so the
        // shared <x-plan-table> doesn't N+1 when rendering chips/links.
        $items = $period->items()
            ->with(['deliverable.project.client'])
            ->get();

        // For now, the plan-table only renders deliverable-backed items.
        // Ad-hoc items (deliverable_id IS NULL) come into play during the
        // weekly review (M4); we ignore them in the planning views.
        $deliverableItems = $items->whereNotNull('deliverable_id');
        $deliverables = $deliverableItems->pluck('deliverable')->filter();
        $allocations = $deliverableItems->pluck('allocated_days', 'deliverable_id')->all();

        return view('plans.show', [
            'period' => $period,
            'kind' => $kind,
            'deliverables' => $deliverables,
            'allocations' => $allocations,
            'totalAllocated' => $period->totalAllocated(),
            'capacity' => $period->capacity(),
            'overUnder' => $period->overUnder(),
        ]);
    }
}
