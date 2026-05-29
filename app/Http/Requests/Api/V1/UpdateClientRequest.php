<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'email', 'max:200'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'employer_id' => [
                'sometimes',
                'integer',
                Rule::exists('employers', 'id')->where('owner_id', $this->user()->id),
            ],
        ];
    }
}
