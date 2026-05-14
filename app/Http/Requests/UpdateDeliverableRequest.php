<?php

namespace App\Http\Requests;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Models\ContactPerson;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('deliverable'));
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
            'target_days' => ['required', 'numeric', 'min:0', 'max:999', 'multiple_of:0.5'],
            'days_spent' => ['nullable', 'numeric', 'min:0', 'max:999', 'multiple_of:0.5'],
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
}
