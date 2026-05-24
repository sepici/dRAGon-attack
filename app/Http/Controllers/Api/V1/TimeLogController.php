<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeLogResource;
use App\Models\TimeLog;
use App\Support\DateInput;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * GET /api/v1/time-logs
 *   ?date=YYYY-MM-DD | "today" | "yesterday" | natural-language
 *   ?from=...   inclusive lower bound (alternative to date)
 *   ?to=...     inclusive upper bound
 *   ?deliverable_id=N
 *   ?ad_hoc=true|false
 *
 * GET /api/v1/time-logs/{id}
 */
class TimeLogController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $query = TimeLog::query()
            ->where('owner_id', auth()->id())
            ->with(['deliverable.project.client'])
            ->orderByDesc('log_date')
            ->orderByDesc('id');

        // Single-date filter takes precedence over from/to.
        if ($dateInput = $request->string('date')->toString()) {
            if ($date = DateInput::parse($dateInput)) {
                $query->whereDate('log_date', $date->toDateString());
            }
        } else {
            $from = DateInput::parse($request->string('from')->toString() ?: null);
            $to = DateInput::parse($request->string('to')->toString() ?: null);
            if ($from) {
                $query->whereDate('log_date', '>=', $from->toDateString());
            }
            if ($to) {
                $query->whereDate('log_date', '<=', $to->toDateString());
            }
        }

        if ($deliverableId = $request->integer('deliverable_id')) {
            $query->where('deliverable_id', $deliverableId);
        }

        if ($request->has('ad_hoc')) {
            $isAdHoc = filter_var($request->input('ad_hoc'), FILTER_VALIDATE_BOOLEAN);
            $isAdHoc
                ? $query->whereNull('deliverable_id')
                : $query->whereNotNull('deliverable_id');
        }

        return TimeLogResource::collection($query->paginate());
    }

    public function show(TimeLog $timeLog): TimeLogResource
    {
        abort_unless($timeLog->owner_id === auth()->id(), 404);
        $timeLog->load(['deliverable.project.client']);

        return new TimeLogResource($timeLog);
    }
}
