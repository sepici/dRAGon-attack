<?php

namespace App\Http\Requests;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Models\ContactPerson;
use App\Models\Milestone;
use App\Models\Project;
use App\Support\TimeUnits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('deliverable'));
    }

    /** See StoreDeliverableRequest::prepareForValidation. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('target_days')) {
            $this->merge([
                'target_hours' => TimeUnits::hoursFromDays((float) $this->input('target_days')),
            ]);
        }

        // "— No milestone —" submits as empty string; normalise to null so the
        // `exists` rule doesn't trip and the model stores NULL (clearing the link).
        if ($this->input('milestone_id') === '' || $this->input('milestone_id') === null) {
            $this->merge(['milestone_id' => null]);
        }
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
            // Optional milestone — must belong to the same project as the deliverable.
            'milestone_id' => [
                'nullable',
                'integer',
                'exists:milestones,id',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $milestone = Milestone::find($value);
                    if ($milestone && (int) $milestone->project_id !== (int) $this->input('project_id')) {
                        $fail('The selected milestone must belong to the chosen project.');
                    }
                },
            ],
            'target_days' => ['required', 'numeric', 'min:0', 'max:250', 'multiple_of:0.5'],
            'target_hours' => ['required', 'numeric', 'min:0', 'max:2000'],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(Status::class)],
            'moscow' => ['nullable', Rule::enum(Moscow::class)],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => [
                'integer',
                'exists:contact_persons,id',
                function ($attribute, $value, $fail) {
                    $project = Project::find($this->input('project_id'));
                    if (! $project) {
                        return;
                    }
                    $contact = ContactPerson::find($value);
                    if ($contact && (int) $contact->client_id !== (int) $project->client_id) {
                        $fail('All responsible contacts must belong to the project\'s client.');
                    }
                },
            ],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            unset($data['target_days']);
        }
        return $data;
    }
}
