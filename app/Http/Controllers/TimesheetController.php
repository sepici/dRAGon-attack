<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Services\TimesheetPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 *     GET  /timesheets                          timesheets.index
 *     POST /timesheets/generate                 timesheets.generate
 *         (optional `month=YYYY-MM` form input; defaults to current month)
 *     GET  /timesheets/{timesheet}/download     timesheets.download
 */
class TimesheetController extends Controller
{
    public function __construct(private readonly TimesheetPdfService $service)
    {
    }

    public function index(): View
    {
        $timesheets = auth()->user()
            ->timesheets()
            ->orderByDesc('month_starts_on')
            ->orderByDesc('generated_at')
            ->get();

        return view('timesheets.index', compact('timesheets'));
    }

    public function generate(): RedirectResponse
    {
        $monthInput = (string) request()->input('month', '');
        $monthStart = $this->parseMonthOrThisMonth($monthInput);

        $timesheet = $this->service->generateForMonth(auth()->user(), $monthStart);

        return redirect()
            ->route('timesheets.index')
            ->with('status', "Timesheet generated for {$timesheet->month_starts_on->format('M Y')}.");
    }

    public function download(Timesheet $timesheet): StreamedResponse
    {
        abort_unless($timesheet->owner_id === auth()->id(), 403);
        abort_unless($timesheet->fileExists(), 404);

        return response()->streamDownload(
            fn () => print(file_get_contents($timesheet->absolutePath())),
            $timesheet->downloadName(),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Accept "YYYY-MM" from the form; fall back to the current month for
     * empty or malformed input. We don't 422 on bad month strings — the form
     * just degrades to "this month" so the button is always useful.
     */
    private function parseMonthOrThisMonth(string $input): CarbonImmutable
    {
        if ($input === '' || ! preg_match('/^\d{4}-\d{2}$/', $input)) {
            return CarbonImmutable::now()->startOfMonth();
        }
        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $input . '-01');
        if (! $parsed || $parsed->format('Y-m') !== $input) {
            return CarbonImmutable::now()->startOfMonth();
        }
        return $parsed->startOfMonth();
    }
}
