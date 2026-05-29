<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ReportPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 *     GET  /reports                       reports.index
 *     POST /reports/generate              reports.generate
 *     GET  /reports/{report}/download     reports.download
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportPdfService $service)
    {
    }

    public function index(): View
    {
        $reports = auth()->user()
            ->reports()
            ->orderByDesc('week_starts_on')
            ->orderByDesc('generated_at')
            ->get();

        return view('reports.index', compact('reports'));
    }

    public function generate(): RedirectResponse
    {
        // Optional multi-employer scope. Empty = all the user's employers.
        // Foreign ids are filtered out inside the service against the user's
        // own employer list.
        $employerIds = (array) request()->input('employer_ids', []);
        $employerIds = array_map('intval', array_filter($employerIds));

        $report = $this->service->generateWeeklyReport(
            auth()->user(),
            $employerIds ?: null,
        );

        return redirect()
            ->route('reports.index')
            ->with('status', "Weekly report generated for week of {$report->week_starts_on->format('d M Y')}.");
    }

    public function download(Report $report): StreamedResponse
    {
        // Owner-scoping: a user can only download their own report.
        abort_unless($report->owner_id === auth()->id(), 403);
        abort_unless($report->fileExists(), 404);

        return response()->streamDownload(
            fn () => print(file_get_contents($report->absolutePath())),
            $report->downloadName(),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
