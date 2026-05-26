<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Moscow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Patch-style update for a milestone. Everything is `sometimes` — agents
 * include only the fields they want to change. target_hours is nullable
 * so an agent can explicitly clear a manual target.
 */
class UpdateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'sometimes',
                Rule::exists('projects', 'id')->where('owner_id', $this->user()->id),
            ],
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'target_hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'moscow' => ['sometimes', 'nullable', Rule::enum(Moscow::class)],
            'scope_complete' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
