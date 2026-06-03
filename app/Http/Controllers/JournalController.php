<?php

namespace App\Http\Controllers;

use App\Enums\PlanKind;
use App\Http\Requests\StoreJournalRequest;
use App\Models\Deliverable;
use App\Models\PlanPeriod;
use App\Models\TimeLog;
use App\Services\DailyJournalService;
use App\Support\EmployerScopedPicker;
use App\Support\TimeUnits;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Daily journal — where the user logs how many hours they spent on what,
 * day by day. This is the source of truth that feeds:
 *
 *   • Deliverable.hours_spent (derived, sum across all time_logs)
 *   • PlanItem.hours_spent    (derived, sum within the plan_period's window)
 *   • The monthly timesheet PDF
 *
 * Routes:
 *   GET  /journal              → today
 *   GET  /journal/{date}       → specific date (YYYY-MM-DD)
 *   POST /journal/{date}       → save
 *
 * The page is strictly self-scoped: each user only sees and edits their own
 * logs. Admins don't use it. Viewers will get a read-only variant later.
 */
class JournalController extends Controller
{
    public function __construct(private readonly DailyJournalService $service)
    {
    }

    public function today(): RedirectResponse
    {
        return redirect()->route('journal.show', ['date' => CarbonImmutable::now()->toDateString()]);
    }

    public function show(string $date): View
    {
        $date = $this->parseDate($date);
        $user = auth()->user();

        // The weekly plan period that contains this date — fetched if it
        // exists, NOT auto-created (we don't want to spam plan_periods just
        // because someone clicked into a different week's journal).
        $period = $this->weeklyPeriodFor($user, $date);

        // Plan items inside that period. Used as the "Planned this week"
        // section of the form. Eager-load the chain so the rows can show
        // project + client without N+1.
        $planItems = $period
            ? $period->items()
                ->whereNotNull('deliverable_id')
                ->with(['deliverable.project.client'])
                ->orderBy('sort_order')->orderBy('id')
                ->get()
            : collect();

        // Existing time_logs for this date, keyed by deliverable_id so the
        // view can pre-fill hours+notes inputs without another lookup.
        $logs = TimeLog::forOwner($user)->forDate($date)->get();
        $deliverableLogs = $logs->filter(fn ($l) => ! is_null($l->deliverable_id))
            ->keyBy('deliverable_id');
        $adHocLogs = $logs->whereNull('deliverable_id')->values();

        // Deliverables that have a log on this date but are NOT in the
        // current week's plan_period — typically because the plan got changed
        // after the log was made. Show them as a separate "Also logged" group
        // so the user can still see and edit them.
        $extraDeliverableIds = $deliverableLogs->keys()->diff(
            $planItems->pluck('deliverable_id')
        );
        $extraDeliverables = $extraDeliverableIds->isEmpty()
            ? collect()
            : Deliverable::with(['project.client'])
                ->whereIn('id', $extraDeliverableIds)
                ->get();

        $totalHours = (float) $logs->sum('hours');

        // Daily target = one workday's worth of hours, regardless of how
        // many days a week the user works. The weekly capacity already
        // encodes "5 days × 8h" vs "6 days × 8h" by being the total.
        $dailyTarget = TimeUnits::HOURS_PER_DAY;

        // Cascading picker data (Employer → Client → Project) for the
        // "Log time on another deliverable" widget. We add the per-project
        // deliverable list on top — already-planned and already-logged-today
        // deliverables are filtered out so the picker can't double-add them.
        $picker = EmployerScopedPicker::forUser($user);
        $picker['deliverablesByProject'] = $this->deliverablesForPicker(
            $user,
            collect($picker['projectsByClient'])->flatten(1)->pluck('id')->all(),
            $planItems->pluck('deliverable_id')->merge($deliverableLogs->keys())->all(),
        );

        return view('journal.show', [
            'date' => $date,
            'period' => $period,
            'planItems' => $planItems,
            'extraDeliverables' => $extraDeliverables,
            'deliverableLogs' => $deliverableLogs,
            'adHocLogs' => $adHocLogs,
            'totalHours' => $totalHours,
            'dailyTarget' => $dailyTarget,
            'picker' => $picker,
            'prevDate' => $date->subDay()->toDateString(),
            'nextDate' => $date->addDay()->toDateString(),
            'isToday' => $date->isSameDay(CarbonImmutable::now()),
        ]);
    }

    /**
     * Deliverables grouped by project_id, with the rows already present in
     * this journal page (planned this week, or already logged on this date)
     * filtered out — the picker shouldn't be able to double-add a row that
     * the user can already see and edit.
     *
     * Completed deliverables are kept but flagged with is_complete=true so
     * the view can render them muted with a "Done" badge.
     *
     * @param  array<int,int>  $projectIds
     * @param  array<int,int>  $excludeIds
     * @return array<int,array<int,array{id:int,name:string,is_complete:bool,project_id:int}>>
     */
    private function deliverablesForPicker(\App\Models\User $user, array $projectIds, array $excludeIds): array
    {
        $out = [];
        foreach ($projectIds as $id) {
            $out[(int) $id] = [];
        }
        if (empty($projectIds)) {
            return $out;
        }

        Deliverable::query()
            ->whereIn('project_id', $projectIds)
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->when(! empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderBy('name')
            ->get(['id', 'name', 'project_id', 'completed_at'])
            ->each(function ($d) use (&$out) {
                $out[(int) $d->project_id][] = [
                    'id' => (int) $d->id,
                    'name' => $d->name,
                    'is_complete' => ! is_null($d->completed_at),
                    'project_id' => (int) $d->project_id,
                ];
            });

        return $out;
    }

    public function store(StoreJournalRequest $request, string $date): RedirectResponse
    {
        $date = $this->parseDate($date);
        $user = $request->user();

        $itemUpdates = $request->itemUpdates();
        $adHocItems = $request->adHocItems();

        // Ownership check: every submitted deliverable_id must belong to a
        // project the user owns. Otherwise we'd let one user log time on
        // someone else's deliverable.
        if (! empty($itemUpdates)) {
            $submittedIds = array_keys($itemUpdates);
            $ownedIds = Deliverable::query()
                ->whereIn('id', $submittedIds)
                ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
                ->pluck('id')
                ->all();

            if (count($ownedIds) !== count($submittedIds)) {
                throw ValidationException::withMessages([
                    'items' => 'One or more deliverables do not belong to you.',
                ]);
            }
        }

        // Ownership check on existing ad-hoc rows.
        $existingAdHocIds = collect($adHocItems)->pluck('id')->filter()->all();
        if (! empty($existingAdHocIds)) {
            $ownedAdHocIds = TimeLog::query()
                ->whereIn('id', $existingAdHocIds)
                ->where('owner_id', $user->id)
                ->whereNull('deliverable_id')
                ->pluck('id')
                ->all();

            if (count($ownedAdHocIds) !== count($existingAdHocIds)) {
                throw ValidationException::withMessages([
                    'ad_hoc' => 'One or more ad-hoc rows do not belong to you.',
                ]);
            }
        }

        $this->service->sync($user, $date, $itemUpdates, $adHocItems);

        return redirect()
            ->route('journal.show', ['date' => $date->toDateString()])
            ->with('status', 'Journal saved.');
    }

    /**
     * Parse YYYY-MM-DD; 404 anything else so we don't end up with malformed
     * routes leaking into the controller. We can't just rely on
     * createFromFormat because Carbon silently rolls '2026-13-01' over to
     * 2027-01-01 — so we round-trip the parsed value back to a string and
     * compare, which catches out-of-range months and days.
     */
    private function parseDate(string $date): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }
        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            abort(404);
        }
        return $parsed->startOfDay();
    }

    private function weeklyPeriodFor($user, CarbonImmutable $date): ?PlanPeriod
    {
        [$start, $end] = PlanPeriod::boundsFor(PlanKind::Weekly, $date);

        return PlanPeriod::query()
            ->where('owner_id', $user->id)
            ->where('kind', PlanKind::Weekly->value)
            ->whereDate('starts_on', $start->toDateString())
            ->first();
    }
}
