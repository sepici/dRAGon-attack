<?php

namespace App\Http\Requests;

use App\Enums\Moscow;
use App\Support\TimeUnits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('milestone'));
    }

    protected function prepareForValidation(): void
    {
        // See StoreMilestoneRequest for why filled() is required here —
        // ConvertEmptyStringsToNull turns '' into null before this runs.
        if ($this->filled('target_days')) {
            $this->merge([
                'target_hours' => TimeUnits::hoursFromDays((float) $this->input('target_days')),
            ]);
        } elseif ($this->has('target_days')) {
            // Empty/null → explicit clear (null out target).
            $this->merge(['target_hours' => null]);
        }
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

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            unset($data['target_days']);
        }
        return $data;
    }
}
