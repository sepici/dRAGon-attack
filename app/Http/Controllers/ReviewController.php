<?php

namespace App\Http\Controllers;

use App\Enums\PlanKind;
use App\Http\Requests\StoreReviewRequest;
use App\Models\PlanPeriod;
use App\Services\WeeklyReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * End-of-week review.
 *
 *     GET  /review                  → retrospective for this week's items
 *     POST /review                  → save the review (transaction)
 *     POST /review/roll-forward     → copy incomplete items into next week
 *
 * Since M8d the page is read-only on hours — those are logged in the
 * /journal day-by-day. The form posts back just `completed` and `notes`.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly WeeklyReviewService $service)
    {
    }

    public function show(): View
    {
        $period = PlanPeriod::findOrCreateCurrentFor(auth()->user(), PlanKind::Weekly);

        // Eager-load the deliverable chain for the table, then hydrate each
        // plan_item's derived hours_spent in one query.
        $period->load(['items.deliverable.project.client']);
        $period->loadHoursSpent();

        $items = $period->items
            ->sortBy([
                fn ($a, $b) => is_null($a->completed_at) <=> is_null($b->completed_at),
                fn ($a, $b) => $a->id <=> $b->id,
            ])
            ->values();

        return view('review.show', [
            'period' => $period,
            'items' => $items,
        ]);
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $period = PlanPeriod::findOrCreateCurrentFor(auth()->user(), PlanKind::Weekly);

        $this->service->process($period, $request->itemUpdates());

        return redirect()
            ->route('review.show')
            ->with('status', 'Review saved.');
    }

    public function rollForward(): RedirectResponse
    {
        $period = PlanPeriod::findOrCreateCurrentFor(auth()->user(), PlanKind::Weekly);

        $this->service->rollForward($period);

        return redirect()
            ->route('review.show')
            ->with('status', 'Incomplete items rolled forward to next week.');
    }
}
