<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Models\Milestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The API speaks hours, not days — the web FormRequests convert at the
 * edge because humans think in days; agents already know the unit.
 */
class StoreDeliverableRequest extends FormRequest
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
            'target_hours' => ['required', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(Status::class)],
            'moscow' => ['nullable', Rule::enum(Moscow::class)],
            // Optional milestone. Closure enforces the same project — a
            // deliverable can't reference a milestone in a different project.
            'milestone_id' => [
                'nullable',
                'integer',
                'exists:milestones,id',
                function ($attribute, $value, $fail) {
                    if (is_null($value)) {
                        return;
                    }
                    $milestone = Milestone::find($value);
                    if ($milestone && (int) $milestone->project_id !== (int) $this->input('project_id')) {
                        $fail('The selected milestone must belong to the same project as the deliverable.');
                    }
                },
            ],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data) && ! isset($data['status'])) {
            $data['status'] = Status::Red->value;
        }
        return $data;
    }
}
