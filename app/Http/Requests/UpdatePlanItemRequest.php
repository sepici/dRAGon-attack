<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership of the parent period is checked in the controller — this
        // request only validates the input shape.
        return $this->user()->isUser();
    }

    public function rules(): array
    {
        return [
            'allocated_hours' => [
                'required', 'numeric', 'min:0', 'max:9999', 'multiple_of:0.5',
            ],
        ];
    }
}
