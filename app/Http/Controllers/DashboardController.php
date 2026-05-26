<?php

namespace App\Http\Controllers;

use App\Enums\PlanKind;
use App\Enums\Status;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\PlanPeriod;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Per-user "what's the situation" landing page.
 *
 * Surfaces:
 *   - Capacity widgets for this week / month / quarter
 *   - Deliverable counts by status (R / A / G / B)
 *   - Deliverables with deadlines in the next 7 days (not yet completed)
 *   - Recently completed deliverables
 *   - Quick-action links into the rest of the app
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        // Auto-create the three current plan periods so the capacity widgets
        // always have something to render (matches the behaviour of /plans/*).
        $weekly = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $monthly = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Monthly);
        $quarterly = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Quarterly);

        // Deliverable status counts, scoped to projects this user owns.
        $rawCounts = Deliverable::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');
        $statusCounts = [
            'R' => (int) ($rawCounts['R'] ?? 0),
            'A' => (int) ($rawCounts['A'] ?? 0),
            'G' => (int) ($rawCounts['G'] ?? 0),
            'B' => (int) ($rawCounts['B'] ?? 0),
        ];

        // Deliverables with deadlines coming up in the next 7 days, not done.
        $today = CarbonImmutable::now()->startOfDay();
        $upcoming = Deliverable::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->whereNull('completed_at')
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$today, $today->addDays(7)])
            ->orderBy('deadline')
            ->with('project.client')
            ->limit(8)
            ->get();

        // Recently completed (last 5). withHoursSpent() so the view can
        // show "Xh (Yd)" without N+1.
        $recentlyCompleted = Deliverable::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->with('project.client')
            ->withHoursSpent()
            ->limit(5)
            ->get();

        // Milestone status counts. Status is derived (depends on child
        // deliverable statuses + scope_complete), so we tally in PHP rather
        // than GROUP BY. Eager-loading the deliverable slice keeps the
        // accessor's per-row query from firing.
        $milestones = Milestone::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->with(['deliverables:id,milestone_id,status'])
            ->get();

        $milestoneCounts = ['R' => 0, 'A' => 0, 'G' => 0, 'B' => 0];
        $scopeNotConfirmedCount = 0;
        foreach ($milestones as $m) {
            $milestoneCounts[$m->status->value]++;
            if ($m->isScopeAmbiguous()) {
                $scopeNotConfirmedCount++;
            }
        }

        return view('dashboard', compact(
            'weekly',
            'monthly',
            'quarterly',
            'statusCounts',
            'upcoming',
            'recentlyCompleted',
            'milestoneCounts',
            'scopeNotConfirmedCount',
        ));
    }
}
