<?php

namespace App\Services;

use App\Models\Employer;
use App\Models\TimeLog;
use App\Models\Timesheet;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates the monthly timesheet PDF.
 *
 * Standard monthly-timesheet grid:
 *   - rows = (employer, project) or (employer, ad-hoc name) pairs
 *   - columns = day 1..N for the chosen month
 *   - cells = SUM(time_logs.hours) for that (row, day)
 *   - right column = row total
 *   - bottom row = per-day totals across all rows
 *   - per-employer summary footer (M13d)
 *   - footer = total hours + count of days worked
 *
 * Stored under storage/app/timesheets/{owner_id}/timesheet-{YYYY-MM}[-scope]-{ts}.pdf.
 *
 * Multi-employer (M13d): caller can pass an explicit list of employer_ids
 * to limit the timesheet to those employers. null = include every employer
 * the user owns (back-compat behaviour, default for callers that don't
 * specify).
 */
class TimesheetPdfService
{
    /**
     * Generate (or regenerate) the monthly timesheet for $user covering the
     * calendar month containing $anyDateInMonth.
     *
     * @param  array<int>|null  $employerIds
     */
    public function generateForMonth(
        User $user,
        CarbonImmutable $anyDateInMonth,
        ?array $employerIds = null,
    ): Timesheet {
        $monthStart = $anyDateInMonth->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $daysInMonth = $monthStart->daysInMonth;
        $now = CarbonImmutable::now();

        [$employerIds, $isAllEmployers] = $this->resolveEmployerScope($user, $employerIds);

        $logs = TimeLog::query()
            ->where('owner_id', $user->id)
            ->whereIn('employer_id', $employerIds)
            ->whereDate('log_date', '>=', $monthStart->toDateString())
            ->whereDate('log_date', '<=', $monthEnd->toDateString())
            ->with(['deliverable.project', 'employer:id,name,is_self'])
            ->get();

        $employersById = Employer::query()
            ->whereIn('id', $employerIds)
            ->orderByDesc('is_self')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        [$rows, $dayTotals, $totalHours, $daysWorked, $employerTotals] =
            $this->buildGrid($logs, $daysInMonth, $employersById);

        $pdf = Pdf::loadView('timesheets.monthly', [
            'user' => $user,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'daysInMonth' => $daysInMonth,
            'generatedAt' => $now,
            'rows' => $rows,
            'dayTotals' => $dayTotals,
            'totalHours' => $totalHours,
            'daysWorked' => $daysWorked,
            'employersById' => $employersById,
            'employerTotals' => $employerTotals,
            'isAllEmployers' => $isAllEmployers,
        ]);
        $pdf->setPaper('a4', 'landscape');

        $scopeSlug = $this->scopeSlug($employersById, $isAllEmployers);
        $relativePath = sprintf(
            'timesheets/%d/timesheet-%s%s-%s.pdf',
            $user->id,
            $monthStart->format('Y-m'),
            $scopeSlug ? '-' . $scopeSlug : '',
            $now->format('YmdHis'),
        );
        Storage::disk('local')->put($relativePath, $pdf->output());

        return Timesheet::create([
            'owner_id' => $user->id,
            'month_starts_on' => $monthStart->toDateString(),
            'generated_at' => $now,
            'file_path' => $relativePath,
        ]);
    }

    /**
     * Resolve the caller's employer-id list to a concrete set:
     *   • null / empty → every employer the user owns
     *   • explicit list → intersect with the user's owned employers
     *                     (protects against tampered form values)
     *
     * @param  array<int>|null  $employerIds
     * @return array{0: array<int>, 1: bool}  [ids, isAllEmployers]
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
     * Pivot raw time_logs into a grid keyed by (employer_id, label) tuple.
     *
     * @param  Collection<int, \App\Models\Employer>  $employersById
     * @return array{0: array<int, array{employer_id: int, employer_name: string, label: string, days: array<int, float>, total: float}>,
     *               1: array<int, float>,
     *               2: float,
     *               3: int,
     *               4: array<int, float>}  rows, dayTotals, totalHours, daysWorked, employerTotals
     */
    private function buildGrid(Collection $logs, int $daysInMonth, Collection $employersById): array
    {
        $rowsByKey = [];

        foreach ($logs as $log) {
            $employerId = (int) $log->employer_id;
            $employerName = $employersById[$employerId]?->name ?? '(unknown)';

            if ($log->deliverable_id && $log->deliverable && $log->deliverable->project) {
                $label = $log->deliverable->project->name;
            } else {
                $label = $log->ad_hoc_name ?? '(unnamed)';
            }

            // Key by tuple so identical project names across employers stay
            // as distinct rows.
            $key = $employerId . '|' . $label;

            if (! isset($rowsByKey[$key])) {
                $rowsByKey[$key] = [
                    'employer_id' => $employerId,
                    'employer_name' => $employerName,
                    'label' => $label,
                    'days' => array_fill(1, $daysInMonth, 0.0),
                    'total' => 0.0,
                ];
            }

            $dayOfMonth = (int) $log->log_date->format('j');
            $rowsByKey[$key]['days'][$dayOfMonth] += (float) $log->hours;
            $rowsByKey[$key]['total'] += (float) $log->hours;
        }

        // Sort rows: employer (Self-first then sort_order/name) → row total desc.
        $employerOrder = $employersById->keys()->flip()->all();
        $rows = array_values($rowsByKey);
        usort($rows, function ($a, $b) use ($employerOrder) {
            $oa = $employerOrder[$a['employer_id']] ?? PHP_INT_MAX;
            $ob = $employerOrder[$b['employer_id']] ?? PHP_INT_MAX;
            if ($oa !== $ob) return $oa <=> $ob;
            return $b['total'] <=> $a['total'];
        });

        $dayTotals = array_fill(1, $daysInMonth, 0.0);
        foreach ($rows as $row) {
            foreach ($row['days'] as $day => $h) {
                $dayTotals[$day] += $h;
            }
        }

        $totalHours = array_sum($dayTotals);
        $daysWorked = count(array_filter($dayTotals, fn ($h) => $h > 0));

        // Per-employer totals (key = employer_id).
        $employerTotals = [];
        foreach ($rows as $row) {
            $employerTotals[$row['employer_id']] =
                ($employerTotals[$row['employer_id']] ?? 0.0) + $row['total'];
        }

        return [$rows, $dayTotals, $totalHours, $daysWorked, $employerTotals];
    }

    /**
     * Filename slug component reflecting the selected employer scope.
     * Empty string when all employers are included.
     */
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
