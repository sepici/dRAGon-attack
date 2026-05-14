<?php

namespace App\Services;

use App\Enums\PlanKind;
use App\Models\PlanPeriod;
use App\Models\Report;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Generates the weekly PDF report.
 *
 * Sections of the PDF:
 *   1. Review of completed week — plan_items completed in this week +
 *      ad-hoc items + total days vs weekly capacity
 *   2. Plan for the new week — plan_items in next week's period
 *   3. Updated 1-month plan — current monthly period items
 *   4. Updated 3-month plan — current quarterly period items
 *
 * The PDF is stored under storage/app/reports/{owner_id}/ with a filename
 * that includes the week date and a generation timestamp so multiple
 * generations of the same week don't overwrite each other.
 */
class ReportPdfService
{
    public function generateWeeklyReport(User $user): Report
    {
        $now = CarbonImmutable::now();

        // Current weekly period (the one we're reporting on)
        $thisWeek = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Weekly);

        // Next week's period — looked up but NOT created. If user hasn't
        // planned next week yet, the section will just be empty.
        $nextWeekStart = CarbonImmutable::parse($thisWeek->starts_on)->addWeek();
        $nextWeek = PlanPeriod::where('owner_id', $user->id)
            ->where('kind', PlanKind::Weekly->value)
            ->whereDate('starts_on', $nextWeekStart->toDateString())
            ->first();

        $thisMonth = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Monthly);
        $thisQuarter = PlanPeriod::findOrCreateCurrentFor($user, PlanKind::Quarterly);

        // Eager-load deliverable chains so the Blade template doesn't N+1
        $with = ['deliverable.project.client'];

        $data = [
            'user' => $user,
            'generatedAt' => $now,

            // Section 1 — the week just completed
            'thisWeek' => $thisWeek,
            'completedItems' => $thisWeek->items()
                ->whereNotNull('deliverable_id')
                ->whereNotNull('completed_at')
                ->with($with)
                ->get(),
            'adHocItems' => $thisWeek->items()
                ->whereNull('deliverable_id')
                ->get(),
            'incompleteItems' => $thisWeek->items()
                ->whereNotNull('deliverable_id')
                ->whereNull('completed_at')
                ->with($with)
                ->get(),
            'weekCapacity' => $thisWeek->capacity(),
            'weekTotalSpent' => (float) $thisWeek->items()->sum('days_spent'),

            // Section 2 — next week's plan
            'nextWeek' => $nextWeek,
            'nextWeekItems' => $nextWeek
                ? $nextWeek->items()->whereNotNull('deliverable_id')->with($with)->get()
                : collect(),

            // Section 3 — monthly
            'thisMonth' => $thisMonth,
            'monthItems' => $thisMonth->items()->whereNotNull('deliverable_id')->with($with)->get(),
            'monthCapacity' => $thisMonth->capacity(),

            // Section 4 — quarterly
            'thisQuarter' => $thisQuarter,
            'quarterItems' => $thisQuarter->items()->whereNotNull('deliverable_id')->with($with)->get(),
            'quarterCapacity' => $thisQuarter->capacity(),
        ];

        // Render to PDF
        $pdf = Pdf::loadView('reports.weekly', $data);
        $pdf->setPaper('a4', 'portrait');

        // Persist file. Relative path goes into the DB; absolute path is used
        // for writing via the local disk.
        $relativePath = sprintf(
            'reports/%d/week-%s-%s.pdf',
            $user->id,
            $thisWeek->starts_on->format('Y-m-d'),
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
}
