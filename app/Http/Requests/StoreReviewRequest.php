<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the end-of-week review submission.
 *
 * As of M8d the review is a retrospective only — hours are logged in the
 * daily journal, not here. The form posts back just:
 *
 *   items[<plan_item_id>][completed]    bool checkbox (optional)
 *   items[<plan_item_id>][notes]        optional text
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
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int,array{notes:?string,completed:bool}>
     */
    public function itemUpdates(): array
    {
        $out = [];
        foreach ((array) $this->input('items', []) as $id => $payload) {
            $out[(int) $id] = [
                'notes' => $payload['notes'] ?? null,
                'completed' => filter_var($payload['completed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }
        return $out;
    }
}
