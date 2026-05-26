<?php

namespace App\Services;

use App\Enums\PlanKind;
use App\Enums\Status;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Processes the end-of-week review for a PlanPeriod (always weekly kind).
 *
 * Since M8c, hours-tracking lives entirely in the daily journal (time_logs).
 * The weekly review is the *retrospective* — for each planned item the user
 * just toggles "done" and writes a note. Nothing here writes hours.
 *
 *   1. For each existing plan_item we received an update for:
 *        - update notes
 *        - if marked complete → set completed_at, status=G
 *        - if NOT marked complete → recolour based on deadline + derived
 *          hours_spent (derived from time_logs sums via PlanItem accessor):
 *              past deadline → R
 *              any hours logged this week → A
 *              else                       → leave status unchanged
 *
 *   2. (Separate method, dedicated button) Roll incomplete items forward
 *      into next week's plan_period.
 */
class WeeklyReviewService
{
    /**
     * @param array<int,array{notes?:?string,completed?:bool}> $itemUpdates
     *        Keyed by plan_item id.
     */
    public function process(PlanPeriod $period, array $itemUpdates): void
    {
        DB::transaction(function () use ($period, $itemUpdates) {
            $this->updateExistingItems($period, $itemUpdates);
        });
    }

    public function rollForward(PlanPeriod $period): PlanPeriod
    {
        if ($period->kind !== PlanKind::Weekly) {
            throw new \InvalidArgumentException('Roll-forward is only valid for weekly periods.');
        }

        return DB::transaction(function () use ($period) {
            $nextStart = CarbonImmutable::parse($period->starts_on)->addWeek();
            $nextEnd = $nextStart->addDays(6);

            $nextPeriod = PlanPeriod::findOrCreateForOwner(
                $period->owner_id,
                PlanKind::Weekly,
                $nextStart,
                $nextEnd,
            );

            // Roll forward BOTH shapes: deliverable allocations and milestone
            // envelopes. The exactly-one invariant is preserved by copying
            // exactly the same id (only one of the two will be non-null).
            $period->items()
                ->whereNull('completed_at')
                ->get()
                ->each(function (PlanItem $item) use ($nextPeriod) {
                    if ($item->deliverable_id) {
                        $dup = $nextPeriod->items()
                            ->where('deliverable_id', $item->deliverable_id)
                            ->exists();
                        if ($dup) {
                            return;
                        }
                        $nextPeriod->items()->create([
                            'deliverable_id' => $item->deliverable_id,
                            'allocated_hours' => $item->allocated_hours,
                            'notes' => $item->notes,
                            'status' => Status::Red,
                        ]);
                    } elseif ($item->milestone_id) {
                        $dup = $nextPeriod->items()
                            ->where('milestone_id', $item->milestone_id)
                            ->exists();
                        if ($dup) {
                            return;
                        }
                        $nextPeriod->items()->create([
                            'milestone_id' => $item->milestone_id,
                            'allocated_hours' => $item->allocated_hours,
                            'notes' => $item->notes,
                            'status' => Status::Red,
                        ]);
                    }
                });

            return $nextPeriod;
        });
    }

    // ---------- internals ---------------------------------------------------

    private function updateExistingItems(PlanPeriod $period, array $itemUpdates): void
    {
        foreach ($itemUpdates as $itemId => $update) {
            /** @var PlanItem|null $item */
            $item = $period->items()->where('id', (int) $itemId)->first();
            if (! $item) {
                continue;
            }

            $markComplete = (bool) ($update['completed'] ?? false);
            $item->notes = $update['notes'] ?? null;

            if ($markComplete) {
                $item->completed_at = $item->completed_at ?? now();
                $item->status = Status::Green;
            } else {
                $item->completed_at = null;
                $item->status = $this->recolour($item);
            }

            $item->save();
        }
    }

    private function recolour(PlanItem $item): Status
    {
        // Either deliverable or milestone is set (exactly one). Take whichever
        // deadline is relevant.
        $deadline = $item->deliverable?->deadline ?? $item->milestone?->deadline;

        if ($deadline && Carbon::parse($deadline)->isPast()) {
            return Status::Red;
        }
        // Derived from time_logs in the period's window. Any hours logged
        // means "in flight" → amber.
        if ((float) $item->hours_spent > 0) {
            return Status::Amber;
        }
        return $item->status; // leave unchanged
    }
}
