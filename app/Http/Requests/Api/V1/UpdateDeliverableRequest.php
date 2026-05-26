<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Models\Deliverable;
use App\Models\Milestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliverableRequest extends FormRequest
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
            'target_hours' => ['sometimes', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'moscow' => ['sometimes', 'nullable', Rule::enum(Moscow::class)],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            // Optional milestone reassignment. When the request includes
            // project_id we check against that; otherwise we fall back to
            // the deliverable's existing project_id.
            'milestone_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:milestones,id',
                function ($attribute, $value, $fail) {
                    if (is_null($value)) {
                        return;
                    }
                    $milestone = Milestone::find($value);
                    if (! $milestone) {
                        return; // exists rule will fail
                    }
                    $effectiveProjectId = $this->input('project_id');
                    if (! $effectiveProjectId) {
                        $deliverable = $this->route('deliverable');
                        $effectiveProjectId = $deliverable instanceof Deliverable
                            ? $deliverable->project_id
                            : null;
                    }
                    if ($effectiveProjectId && (int) $milestone->project_id !== (int) $effectiveProjectId) {
                        $fail('The selected milestone must belong to the same project as the deliverable.');
                    }
                },
            ],
        ];
    }
}
