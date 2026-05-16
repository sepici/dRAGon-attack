<?php

namespace App\Http\Requests;

use App\Support\TimeUnits;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership of the parent period is checked in the controller — this
        // request only validates the input shape.
        return $this->user()->isUser();
    }

    /** Convert the days input → hours for storage. */
    protected function prepareForValidation(): void
    {
        if ($this->has('allocated_days') && $this->input('allocated_days') !== '') {
            $this->merge([
                'allocated_hours' => TimeUnits::hoursFromDays((float) $this->input('allocated_days')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'allocated_days' => ['required', 'numeric', 'min:0', 'max:250', 'multiple_of:0.5'],
            'allocated_hours' => ['required', 'numeric', 'min:0', 'max:2000'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            unset($data['allocated_days']);
        }
        return $data;
    }
}
