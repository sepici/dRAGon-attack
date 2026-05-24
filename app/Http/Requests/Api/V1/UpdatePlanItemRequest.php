<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Patch a plan_item: allocation, notes, completion. We don't allow moving
 * between periods or swapping the deliverable — that's a delete + create.
 */
class UpdatePlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    public function rules(): array
    {
        return [
            'allocated_hours' => ['sometimes', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'completed_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
