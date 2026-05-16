<?php

namespace App\Services;

use App\Models\TimeLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applies a daily-journal submission to time_logs.
 *
 * Semantics, in plain English:
 *   • Deliverable-linked rows: one time_log per (user, date, deliverable).
 *     If the user submits hours > 0 we upsert; if they submit hours = 0
 *     (or leave it blank) we delete the row if it existed.
 *   • Ad-hoc rows: each row stands on its own. Rows that came back with
 *     their id intact get updated. New rows (no id) get created. Existing
 *     ad-hoc logs for this date whose id is NOT in the submitted set are
 *     deleted — that's how the "remove row" button on the form takes effect.
 *
 * Ownership is the controller's job, not this service's. By the time we get
 * here, every $deliverableId and every existing $row['id'] is known to belong
 * to $user. We don't double-check.
 */
class DailyJournalService
{
    /**
     * @param array<int,array{hours:float,notes:?string}> $itemUpdates
     *        Keyed by deliverable_id.
     * @param array<int,array{id:?int,name:string,hours:float,notes:?string}> $adHocItems
     */
    public function sync(
        User $user,
        CarbonImmutable|string $date,
        array $itemUpdates,
        array $adHocItems,
    ): void {
        $dateStr = $date instanceof CarbonImmutable ? $date->toDateString() : (string) $date;

        DB::transaction(function () use ($user, $dateStr, $itemUpdates, $adHocItems) {
            $this->syncDeliverableItems($user, $dateStr, $itemUpdates);
            $this->syncAdHocItems($user, $dateStr, $adHocItems);
        });
    }

    /**
     * Upsert / delete one time_log per deliverable for this date.
     */
    private function syncDeliverableItems(User $user, string $date, array $itemUpdates): void
    {
        foreach ($itemUpdates as $deliverableId => $row) {
            $hours = (float) ($row['hours'] ?? 0);
            $notes = $row['notes'] ?? null;

            /** @var TimeLog|null $existing */
            $existing = TimeLog::query()
                ->where('owner_id', $user->id)
                ->whereDate('log_date', $date)
                ->where('deliverable_id', $deliverableId)
                ->first();

            if ($hours <= 0) {
                // User cleared the field. Remove the log if it exists.
                $existing?->delete();
                continue;
            }

            if ($existing) {
                $existing->update(['hours' => $hours, 'notes' => $notes]);
            } else {
                TimeLog::create([
                    'owner_id' => $user->id,
                    'log_date' => $date,
                    'deliverable_id' => $deliverableId,
                    'hours' => $hours,
                    'notes' => $notes,
                ]);
            }
        }
    }

    /**
     * Update existing ad-hoc rows by id, create new ones, delete the ones
     * the user removed from the form.
     */
    private function syncAdHocItems(User $user, string $date, array $adHocItems): void
    {
        /** @var Collection<int,TimeLog> $existingAdHoc */
        $existingAdHoc = TimeLog::query()
            ->where('owner_id', $user->id)
            ->whereDate('log_date', $date)
            ->whereNull('deliverable_id')
            ->get()
            ->keyBy('id');

        $submittedIds = [];

        foreach ($adHocItems as $row) {
            $id = $row['id'] ?? null;
            $hours = (float) ($row['hours'] ?? 0);

            if ($id && ($log = $existingAdHoc->get($id))) {
                $log->update([
                    'ad_hoc_name' => $row['name'],
                    'hours' => $hours,
                    'notes' => $row['notes'] ?? null,
                ]);
                $submittedIds[] = $id;
                continue;
            }

            // New row: only persist if hours > 0 (zero-hour ad-hoc rows are
            // typically half-typed mistakes the user didn't fill in).
            if ($hours > 0) {
                TimeLog::create([
                    'owner_id' => $user->id,
                    'log_date' => $date,
                    'deliverable_id' => null,
                    'ad_hoc_name' => $row['name'],
                    'hours' => $hours,
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        }

        // Remove existing ad-hoc rows the user didn't resubmit.
        $toRemove = $existingAdHoc->keys()->diff($submittedIds);
        if ($toRemove->isNotEmpty()) {
            TimeLog::query()->whereIn('id', $toRemove)->delete();
        }
    }
}
