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
 *     GET  /review                  → review page for this week's items
 *     POST /review                  → save the review (transaction)
 *     POST /review/roll-forward     → copy incomplete items into next week
 */
class ReviewController extends Controller
{
    public function __construct(private readonly WeeklyReviewService $service)
    {
    }

    public function show(): View
    {
        $period = PlanPeriod::findOrCreateCurrentFor(auth()->user(), PlanKind::Weekly);

        $items = $period->items()
            ->with(['deliverable.project.client'])
            ->orderByRaw('completed_at IS NULL DESC')  // unfinished first
            ->orderBy('id')
            ->get();

        return view('review.show', [
            'period' => $period,
            'items' => $items,
        ]);
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $period = PlanPeriod::findOrCreateCurrentFor(auth()->user(), PlanKind::Weekly);

        $this->service->process(
            $period,
            $request->itemUpdates(),
            $request->adHocItems(),
        );

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
