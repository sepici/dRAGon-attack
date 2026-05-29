<?php

namespace App\Services;

use App\Enums\PlanKind;
use App\Models\Employer;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Models\Report;
use App\Models\TimeLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates the weekly PDF report.
 *
 * Sections of the PDF:
 *   1. Review of completed week — plan_items completed in this week +
 *      ad-hoc time-logs + total days vs weekly capacity
 *   2. Plan for the new week — plan_items in next week's period
 *   3. Updated 1-month plan — current monthly period items
 *   4. Updated 3-month plan — current quarterly period items
 *
 * The PDF is stored under storage/app/reports/{owner_id}/ with a filename
 * that includes the week date, a generation timestamp, and the employer
 * scope slug when the report is scoped to a subset of the user's employers.
 *
 * Multi-employer (M13d): caller can pass an explicit list of employer_ids.
 * Plan items + ad-hoc logs are filtered to those employers and each row in
 * the rendered partial shows an employer chip.
 */
class ReportPdfService
{
    /**
     * @param  array<int>|null  $employerIds
     */
    public function generateWeeklyReport(User $user, ?array $employerIds = null): Report
    {
        $now = CarbonImmutable::now();

        [$employerIds, $isAllEmployers] = $this->resolveEmployerScope($user, $employerIds);

        $thisWeek = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);
        $nextWeekStart = CarbonImmutable::parse($thisWeek->starts_on)->addWeek();
        $nextWeek = PlanPeriod::where('owner_id', $user->id)
            ->where('kind', PlanKind::Weekly->value)
            ->whereDate('starts_on', $nextWeekStart->toDateString())
            ->first();
        $thisMonth = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Monthly);
        $thisQuarter = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Quarterly);

        $with = [
            'deliverable.project.client:id,employer_id',
            'deliverable.milestone',
            'milestone.project.client:id,employer_id',
        ];

        // Load + filter items per period.
        $thisWeek->setRelation(
            'items',
            $this->loadItemsForPeriod($thisWeek, $employerIds, $with),
        );
        $thisWeek->loadHoursSpent();

        $thisMonth->setRelation(
            'items',
            $this->loadItemsForPeriod($thisMonth, $employerIds, $with),
        );
        $thisMonth->loadHoursSpent();

        $thisQuarter->setRelation(
            'items',
            $this->loadItemsForPeriod($thisQuarter, $employerIds, $with),
        );
        $thisQuarter->loadHoursSpent();

        if ($nextWeek) {
            $nextWeek->setRelation(
                'items',
                $this->loadItemsForPeriod($nextWeek, $employerIds, $with),
            );
            $nextWeek->loadHoursSpent();
        }

        $employersById = Employer::query()
            ->whereIn('id', $employerIds)
            ->orderByDesc('is_self')->orderBy('sort_order')->orderBy('name')
            ->get()
            ->keyBy('id');

        $data = [
            'user' => $user,
            'generatedAt' => $now,

            // Section 1 — the week just completed
            'thisWeek' => $thisWeek,
            'completedItems' => $thisWeek->items->whereNotNull('completed_at')->values(),
            'adHocItems' => TimeLog::query()
                ->where('owner_id', $user->id)
                ->whereNull('deliverable_id')
                ->whereIn('employer_id', $employerIds)
                ->whereDate('log_date', '>=', $thisWeek->starts_on)
                ->whereDate('log_date', '<=', $thisWeek->ends_on)
                ->with('employer:id,name,is_self')
                ->orderBy('log_date')->orderBy('id')
                ->get(),
            'incompleteItems' => $thisWeek->items->whereNull('completed_at')->values(),
            'weekCapacity' => $thisWeek->capacity(),
            'weekTotalSpent' => $thisWeek->totalSpent(),

            'nextWeek' => $nextWeek,
            'nextWeekItems' => $nextWeek ? $nextWeek->items : collect(),

            'thisMonth' => $thisMonth,
            'monthItems' => $thisMonth->items,
            'monthCapacity' => $thisMonth->capacity(),

            'thisQuarter' => $thisQuarter,
            'quarterItems' => $thisQuarter->items,
            'quarterCapacity' => $thisQuarter->capacity(),

            // Scope info for the template + partial.
            'employersById' => $employersById,
            'isAllEmployers' => $isAllEmployers,
        ];

        $pdf = Pdf::loadView('reports.weekly', $data);
        $pdf->setPaper('a4', 'portrait');

        $scopeSlug = $this->scopeSlug($employersById, $isAllEmployers);
        $relativePath = sprintf(
            'reports/%d/week-%s%s-%s.pdf',
            $user->id,
            $thisWeek->starts_on->format('Y-m-d'),
            $scopeSlug ? '-' . $scopeSlug : '',
            $now->format('YmdHis'),
        );
        Storage::disk('local')->put($relativePath, $pdf->output());

        return Report::create([
            'owner_id' => $user->id,
            'week_starts_on' => $thisWeek->starts_on->toDateString(),
            'generated_at' => $now,
            'file_path' => $relativePath,
        ]);
    }

    /**
     * Resolve employer scope, mirroring TimesheetPdfService.
     *
     * @param  array<int>|null  $employerIds
     * @return array{0: array<int>, 1: bool}
     */
    private function resolveEmployerScope(User $user, ?array $employerIds): array
    {
        $owned = $user->employers()->pluck('id')->all();
        if (empty($employerIds)) {
            return [$owned, true];
        }
        $intersect = array_values(array_intersect(
            $owned,
            array_map('intval', $employerIds),
        ));
        $isAll = count($intersect) === count($owned);
        return [$intersect ?: $owned, $isAll];
    }

    /**
     * Load a period's plan_items, scoped to the given employer ids via the
     * deliverable→client and milestone→client chains. Items get the standard
     * eager-load chain attached.
     */
    private function loadItemsForPeriod(PlanPeriod $period, array $employerIds, array $with): Collection
    {
        return PlanItem::query()
            ->where('plan_period_id', $period->id)
            ->with($with)
            ->where(function ($q) use ($employerIds) {
                $q->whereHas('deliverable.project.client', fn ($c) => $c->whereIn('employer_id', $employerIds))
                  ->orWhereHas('milestone.project.client', fn ($c) => $c->whereIn('employer_id', $employerIds));
            })
            ->get();
    }

    private function scopeSlug(Collection $employersById, bool $isAllEmployers): string
    {
        if ($isAllEmployers) {
            return '';
        }
        return $employersById
            ->map(fn ($e) => Str::slug($e->name) ?: 'employer-' . $e->id)
            ->implode('+');
    }
}
