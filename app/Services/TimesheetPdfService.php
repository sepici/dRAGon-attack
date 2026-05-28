<?php

namespace App\Services;

use App\Models\TimeLog;
use App\Models\Timesheet;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Generates the monthly timesheet PDF.
 *
 * Standard monthly-timesheet grid:
 *   - rows = projects + distinct ad-hoc names
 *   - columns = day 1..N for the chosen month
 *   - cells = SUM(time_logs.hours) for that (row, day)
 *   - right column = row total
 *   - bottom row = per-day totals
 *   - footer = total hours + count of days worked
 *
 * Stored under storage/app/timesheets/{owner_id}/timesheet-{YYYY-MM}-{ts}.pdf.
 */
class TimesheetPdfService
{
    /**
     * Generate (or regenerate) the monthly timesheet for $user covering the
     * calendar month containing $anyDateInMonth.
     */
    public function generateForMonth(User $user, CarbonImmutable $anyDateInMonth): Timesheet
    {
        $monthStart = $anyDateInMonth->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $daysInMonth = $monthStart->daysInMonth;
        $now = CarbonImmutable::now();

        // Load every time_log this user made in the month, with the chain so
        // we can group by project without N+1.
        $logs = TimeLog::query()
            ->where('owner_id', $user->id)
            ->whereDate('log_date', '>=', $monthStart->toDateString())
            ->whereDate('log_date', '<=', $monthEnd->toDateString())
            ->with(['deliverable.project'])
            ->get();

        [$rows, $dayTotals, $totalHours, $daysWorked] = $this->buildGrid($logs, $daysInMonth, $monthStart);

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
        ]);
        $pdf->setPaper('a4', 'landscape');

        $relativePath = sprintf(
            'timesheets/%d/timesheet-%s-%s.pdf',
            $user->id,
            $monthStart->format('Y-m'),
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
     * Pivot raw time_logs into a grid keyed by row label.
     *
     * @return array{0: array<int, array{label: string, days: array<int, float>, total: float}>,
     *               1: array<int, float>,
     *               2: float,
     *               3: int}
     */
    private function buildGrid(Collection $logs, int $daysInMonth, CarbonImmutable $monthStart): array
    {
        // Bucket logs by row label.
        //   deliverable-linked → project name
        //   ad-hoc             → ad_hoc_name
        $rowsByLabel = [];

        foreach ($logs as $log) {
            if ($log->deliverable_id && $log->deliverable && $log->deliverable->project) {
                $label = $log->deliverable->project->name;
            } else {
                $label = $log->ad_hoc_name ?? '(unnamed)';
            }

            if (! isset($rowsByLabel[$label])) {
                $rowsByLabel[$label] = [
                    'label' => $label,
                    'days' => array_fill(1, $daysInMonth, 0.0),
                    'total' => 0.0,
                ];
            }

            $dayOfMonth = (int) $log->log_date->format('j');
            $rowsByLabel[$label]['days'][$dayOfMonth] += (float) $log->hours;
            $rowsByLabel[$label]['total'] += (float) $log->hours;
        }

        // Sort rows: largest total first (most-worked-on at the top).
        $rows = array_values($rowsByLabel);
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        // Per-day totals across all rows.
        $dayTotals = array_fill(1, $daysInMonth, 0.0);
        foreach ($rows as $row) {
            foreach ($row['days'] as $day => $h) {
                $dayTotals[$day] += $h;
            }
        }

        $totalHours = array_sum($dayTotals);
        $daysWorked = count(array_filter($dayTotals, fn ($h) => $h > 0));

        return [$rows, $dayTotals, $totalHours, $daysWorked];
    }
}
