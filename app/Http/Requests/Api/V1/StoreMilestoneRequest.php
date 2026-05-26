<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Moscow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * API speaks hours, not days — the web FormRequests convert at the edge
 * because humans think in days; agents already know the unit. target_hours
 * is optional (milestone may have no manual target).
 */
class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                Rule::exists('projects', 'id')->where('owner_id', $this->user()->id),
            ],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'target_hours' => ['nullable', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
            'moscow' => ['nullable', Rule::enum(Moscow::class)],
            'scope_complete' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data) && ! isset($data['scope_complete'])) {
            $data['scope_complete'] = false;
        }
        return $data;
    }
}
