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
 * Encapsulates the transactional logic so the controller stays tiny:
 *
 *   1. For each existing plan_item we received an update for:
 *        - update days_spent + notes
 *        - if marked complete → set completed_at, status=G, increment the
 *          underlying Deliverable.days_spent (cumulative master counter)
 *        - if NOT marked complete → recolour based on deadline + spend:
 *              past deadline → R
 *              any days spent → A
 *              else           → leave status unchanged
 *
 *   2. For each ad-hoc item submitted (unplanned work):
 *        - insert a new plan_item with deliverable_id=NULL, ad_hoc_name set,
 *          completed_at=now, status=G
 *
 *   3. (Separate method, called from a dedicated button) Roll incomplete
 *      items forward into next week's plan_period.
 */
class WeeklyReviewService
{
    /**
     * @param array<int,array{days_spent?:float,notes?:?string,completed?:bool}> $itemUpdates
     *        Keyed by plan_item id.
     * @param array<int,array{name:string,days_spent:float,notes?:?string}> $adHocItems
     */
    public function process(PlanPeriod $period, array $itemUpdates, array $adHocItems): void
    {
        DB::transaction(function () use ($period, $itemUpdates, $adHocItems) {
            $this->updateExistingItems($period, $itemUpdates);
            $this->createAdHocItems($period, $adHocItems);
        });
    }

    /**
     * Copy not-yet-completed plan_items from $period to the next weekly
     * period (creating that period if it doesn't exist).
     *
     * Idempotent for the same item — items already present on the next
     * period (matched by deliverable_id) are skipped, so the user can
     * click the button twice without producing duplicates.
     */
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

            $period->items()
                ->whereNotNull('deliverable_id')
                ->whereNull('completed_at')
                ->get()
                ->each(function (PlanItem $item) use ($nextPeriod) {
                    // Skip if already in next period.
                    $exists = $nextPeriod->items()
                        ->where('deliverable_id', $item->deliverable_id)
                        ->exists();
                    if ($exists) {
                        return;
                    }
                    $nextPeriod->items()->create([
                        'deliverable_id' => $item->deliverable_id,
                        'allocated_days' => $item->allocated_days,
                        'days_spent' => 0,
                        'notes' => $item->notes,
                        'status' => Status::Red,
                    ]);
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
                continue; // ignore stale ids (item was deleted between page-load and submit)
            }

            $daysSpent = (float) ($update['days_spent'] ?? 0);
            $previousDaysSpent = (float) $item->days_spent;
            $previouslyCompleted = ! is_null($item->completed_at);
            $markComplete = (bool) ($update['completed'] ?? false);

            $item->days_spent = $daysSpent;
            $item->notes = $update['notes'] ?? null;

            if ($markComplete) {
                $item->completed_at = $item->completed_at ?? now();
                $item->status = Status::Green;
            } else {
                $item->completed_at = null;
                $item->status = $this->recolour($item);
            }

            $item->save();

            // Master deliverable.days_spent counter: only roll the delta of
            // an explicitly-completed item back to the Deliverable, and only
            // the first time. (Repeated saves of an already-completed item
            // don't keep adding.)
            if ($markComplete && ! $previouslyCompleted && $item->deliverable_id) {
                $item->deliverable->increment('days_spent', $daysSpent);
            }
            // If user un-completes an item, withdraw the days from the
            // deliverable counter.
            if (! $markComplete && $previouslyCompleted && $item->deliverable_id) {
                $item->deliverable->decrement('days_spent', $previousDaysSpent);
            }
        }
    }

    private function recolour(PlanItem $item): Status
    {
        $deliverable = $item->deliverable;

        if ($deliverable && $deliverable->deadline && Carbon::parse($deliverable->deadline)->isPast()) {
            return Status::Red;
        }
        if ((float) $item->days_spent > 0) {
            return Status::Amber;
        }
        return $item->status; // leave unchanged
    }

    private function createAdHocItems(PlanPeriod $period, array $adHocItems): void
    {
        foreach ($adHocItems as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue; // skip blank rows from dynamic add-row UI
            }

            $period->items()->create([
                'deliverable_id' => null,
                'ad_hoc_name' => $name,
                'ad_hoc_notes' => $entry['notes'] ?? null,
                'allocated_days' => 0,
                'days_spent' => (float) ($entry['days_spent'] ?? 0),
                'completed_at' => now(),
                'status' => Status::Green,
            ]);
        }
    }
}
