<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the end-of-week review submission.
 *
 * Expected shape:
 *   items[<plan_item_id>][completed]   bool checkbox (optional)
 *   items[<plan_item_id>][days_spent]  decimal, 0.5 increments
 *   items[<plan_item_id>][notes]       optional text
 *
 *   ad_hoc[<n>][name]        non-empty string (rows with empty name are
 *                             quietly dropped server-side)
 *   ad_hoc[<n>][days_spent]  decimal, 0.5 increments
 *   ad_hoc[<n>][notes]       optional text
 *
 * Note: the controller separately enforces that all referenced plan_item
 * ids actually belong to the auth user's current weekly period.
 */
class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isUser();
    }

    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array'],
            'items.*.completed' => ['nullable', 'boolean'],
            'items.*.days_spent' => ['nullable', 'numeric', 'min:0', 'max:999', 'multiple_of:0.5'],
            'items.*.notes' => ['nullable', 'string'],

            'ad_hoc' => ['nullable', 'array'],
            'ad_hoc.*.name' => ['nullable', 'string', 'max:200'],
            'ad_hoc.*.days_spent' => ['nullable', 'numeric', 'min:0', 'max:999', 'multiple_of:0.5'],
            'ad_hoc.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Convenience: get items map shaped for WeeklyReviewService::process().
     *
     * @return array<int,array{days_spent:float,notes:?string,completed:bool}>
     */
    public function itemUpdates(): array
    {
        $out = [];
        foreach ((array) $this->input('items', []) as $id => $payload) {
            $out[(int) $id] = [
                'days_spent' => (float) ($payload['days_spent'] ?? 0),
                'notes' => $payload['notes'] ?? null,
                'completed' => filter_var($payload['completed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }
        return $out;
    }

    /**
     * Convenience: get ad-hoc rows shaped for the service. Empty-name rows
     * are filtered out here as well (defensive — the service does it too).
     *
     * @return array<int,array{name:string,days_spent:float,notes:?string}>
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
                'name' => $name,
                'days_spent' => (float) ($row['days_spent'] ?? 0),
                'notes' => $row['notes'] ?? null,
            ];
        }
        return $out;
    }
}
