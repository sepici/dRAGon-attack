<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a daily-journal save for a specific date.
 *
 * Expected shape:
 *   items[<deliverable_id>][hours]   decimal, 0.5 increments, max 24h/day
 *   items[<deliverable_id>][notes]   optional text
 *
 *   ad_hoc[<n>][id]      optional integer — existing time_log id for this row
 *                         (set when editing a row that was already saved).
 *                         Absent when adding a brand-new row.
 *   ad_hoc[<n>][name]    non-empty string (rows with empty name are quietly
 *                         dropped server-side); max 200 chars
 *   ad_hoc[<n>][hours]   decimal, 0.5 increments, max 24h/day
 *   ad_hoc[<n>][notes]   optional text
 *
 * The controller separately enforces ownership of the referenced deliverables
 * and ad-hoc time_logs (see JournalController::store()).
 */
class StoreJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isUser();
    }

    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array'],
            'items.*.hours' => ['nullable', 'numeric', 'min:0', 'max:24', 'multiple_of:0.5'],
            'items.*.notes' => ['nullable', 'string'],

            'ad_hoc' => ['nullable', 'array'],
            'ad_hoc.*.id' => ['nullable', 'integer'],
            'ad_hoc.*.name' => ['nullable', 'string', 'max:200'],
            'ad_hoc.*.hours' => ['nullable', 'numeric', 'min:0', 'max:24', 'multiple_of:0.5'],
            'ad_hoc.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Deliverable-keyed updates. Hours of 0 mean "remove the log for this
     * deliverable on this date"; the service handles that.
     *
     * @return array<int,array{hours:float,notes:?string}>
     */
    public function itemUpdates(): array
    {
        $out = [];
        foreach ((array) $this->input('items', []) as $deliverableId => $payload) {
            $out[(int) $deliverableId] = [
                'hours' => (float) ($payload['hours'] ?? 0),
                'notes' => $payload['notes'] ?? null,
            ];
        }
        return $out;
    }

    /**
     * Ad-hoc rows. Existing rows carry an `id`; new rows don't.
     * Rows with an empty name are dropped here so the service doesn't have
     * to filter again.
     *
     * @return array<int,array{id:?int,name:string,hours:float,notes:?string}>
     */
    public function adHocItems(): array
    {
        $out = [];
        foreach ((array) $this->input('ad_hoc', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'name' => $name,
                'hours' => (float) ($row['hours'] ?? 0),
                'notes' => $row['notes'] ?? null,
            ];
        }
        return $out;
    }
}
