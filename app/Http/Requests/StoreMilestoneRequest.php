<?php

namespace App\Http\Requests;

use App\Enums\Moscow;
use App\Support\TimeUnits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Web-form input for creating a milestone. Days on input → hours stored.
 */
class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Milestone::class);
    }

    protected function prepareForValidation(): void
    {
        // Optional target — convert days → hours if provided. Use filled()
        // (not has() + !== '') because the ConvertEmptyStringsToNull global
        // middleware turns an empty form value into null BEFORE this method
        // runs; without filled() we'd happily call hoursFromDays((float) null)
        // and write 0 instead of NULL.
        if ($this->filled('target_days')) {
            $this->merge([
                'target_hours' => TimeUnits::hoursFromDays((float) $this->input('target_days')),
            ]);
        } else {
            $this->merge(['target_hours' => null]);
        }
        // Default the checkbox to false when unchecked (HTML omits the value).
        $this->merge([
            'scope_complete' => $this->boolean('scope_complete'),
        ]);
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
            'target_days' => ['nullable', 'numeric', 'min:0', 'max:250', 'multiple_of:0.5'],
            'target_hours' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'deadline' => ['nullable', 'date'],
            'moscow' => ['nullable', Rule::enum(Moscow::class)],
            'scope_complete' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }

    /** Strip the days-only convenience field; it's not a column. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            unset($data['target_days']);
        }
        return $data;
    }
}
