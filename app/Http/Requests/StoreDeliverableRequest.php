<?php

namespace App\Http\Requests;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Models\ContactPerson;
use App\Models\Project;
use App\Support\TimeUnits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Deliverable::class);
    }

    /**
     * The deliverable form accepts target *days* (because that's how humans
     * scope work — "this is a 3-day job"). Storage is in hours, so we
     * convert before validation runs against the underlying column rule.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('target_days') && $this->input('target_days') !== '') {
            $this->merge([
                'target_hours' => TimeUnits::hoursFromDays((float) $this->input('target_days')),
            ]);
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
            // Days input — half-day increments, capped at ~250 working days.
            'target_days' => ['required', 'numeric', 'min:0', 'max:250', 'multiple_of:0.5'],
            // Derived from target_days in prepareForValidation; we re-validate
            // for sanity (Eloquent will fillable-strip it if not in rules).
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
                        return; // project_id rule will fail
                    }
                    $contact = ContactPerson::find($value);
                    if ($contact && (int) $contact->client_id !== (int) $project->client_id) {
                        $fail('All responsible contacts must belong to the project\'s client.');
                    }
                },
            ],
        ];
    }

    /**
     * Strip target_days from the data that flows to Deliverable::create() —
     * it's not a real column, only an input convention.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            unset($data['target_days']);
        }
        return $data;
    }
}
