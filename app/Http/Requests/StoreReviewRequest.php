<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the end-of-week review submission.
 *
 * Expected shape:
 *   items[<plan_item_id>][completed]    bool checkbox (optional)
 *   items[<plan_item_id>][hours_spent]  decimal, 0.5 increments
 *   items[<plan_item_id>][notes]        optional text
 *
 *   ad_hoc[<n>][name]         non-empty string (rows with empty name are
 *                              quietly dropped server-side)
 *   ad_hoc[<n>][hours_spent]  decimal, 0.5 increments
 *   ad_hoc[<n>][notes]        optional text
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
            'items.*.hours_spent' => ['nullable', 'numeric', 'min:0', 'max:9999', 'multiple_of:0.5'],
            'items.*.notes' => ['nullable', 'string'],

            'ad_hoc' => ['nullable', 'array'],
            'ad_hoc.*.name' => ['nullable', 'string', 'max:200'],
            'ad_hoc.*.hours_spent' => ['nullable', 'numeric', 'min:0', 'max:9999', 'multiple_of:0.5'],
            'ad_hoc.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int,array{hours_spent:float,notes:?string,completed:bool}>
     */
    public function itemUpdates(): array
    {
        $out = [];
        foreach ((array) $this->input('items', []) as $id => $payload) {
            $out[(int) $id] = [
                'hours_spent' => (float) ($payload['hours_spent'] ?? 0),
                'notes' => $payload['notes'] ?? null,
                'completed' => filter_var($payload['completed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }
        return $out;
    }

    /**
     * @return array<int,array{name:string,hours_spent:float,notes:?string}>
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
                'hours_spent' => (float) ($row['hours_spent'] ?? 0),
                'notes' => $row['notes'] ?? null,
            ];
        }
        return $out;
    }
}
