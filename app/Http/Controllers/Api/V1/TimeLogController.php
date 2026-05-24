<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTimeLogRequest;
use App\Http\Requests\Api\V1\UpdateTimeLogRequest;
use App\Http\Resources\TimeLogResource;
use App\Models\TimeLog;
use App\Support\DateInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Read endpoints (M9b): index, show.
 * Write endpoints (M9c): store, update, destroy.
 *
 * POST /api/v1/time-logs is the agent's bread-and-butter — accepts fuzzy
 * deliverable_name, relative dates, and either deliverable_id OR ad_hoc_name.
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

    public function store(StoreTimeLogRequest $request): JsonResponse
    {
        $data = $request->validated();

        $log = TimeLog::create([
            'owner_id' => $request->user()->id,
            'log_date' => $data['date'],
            'deliverable_id' => $data['deliverable_id'] ?? null,
            'ad_hoc_name' => $data['ad_hoc_name'] ?? null,
            'hours' => $data['hours'],
            'notes' => $data['notes'] ?? null,
        ]);

        $log->load(['deliverable.project.client']);

        return (new TimeLogResource($log))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTimeLogRequest $request, TimeLog $timeLog): TimeLogResource
    {
        abort_unless($timeLog->owner_id === auth()->id(), 404);

        $data = $request->validated();
        if (array_key_exists('date', $data)) {
            $data['log_date'] = $data['date'];
            unset($data['date']);
        }

        // ad_hoc_name only writable on rows that are actually ad-hoc.
        if (isset($data['ad_hoc_name']) && ! is_null($timeLog->deliverable_id)) {
            unset($data['ad_hoc_name']);
        }

        $timeLog->update($data);
        $timeLog->load(['deliverable.project.client']);

        return new TimeLogResource($timeLog);
    }

    public function destroy(TimeLog $timeLog): JsonResponse
    {
        abort_unless($timeLog->owner_id === auth()->id(), 404);
        $timeLog->delete();

        return response()->json(null, 204);
    }
}
