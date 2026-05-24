<?php

namespace App\Http\Requests\Api\V1;

use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Patch-style update for a single time_log. Only the submitted fields are
 * touched. We don't allow flipping a deliverable-linked log to ad-hoc or
 * vice versa — that's a delete + create.
 */
class UpdateTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('date')) {
            if ($parsed = DateInput::parse((string) $this->input('date'))) {
                $this->merge(['date' => $parsed->toDateString()]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'hours' => ['sometimes', 'numeric', 'min:0', 'max:24', 'multiple_of:0.5'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'ad_hoc_name' => ['sometimes', 'string', 'max:200'],
        ];
    }
}
