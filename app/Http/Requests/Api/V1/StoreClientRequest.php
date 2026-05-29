<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    /**
     * Default employer_id to the caller's Self when missing. Mirrors the web
     * form's "auto-fill Self when only Self exists" behaviour for API
     * callers that don't yet think in terms of employers.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('employer_id')) {
            $this->merge(['employer_id' => $this->user()->selfEmployer()->id]);
        }
    }

    public function rules(): array
    {
        return [
            'employer_id' => [
                'required',
                'integer',
                Rule::exists('employers', 'id')->where('owner_id', $this->user()->id),
            ],
            'legal_name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
