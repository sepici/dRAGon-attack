<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
    }

    protected function prepareForValidation(): void
    {
        // Auto-fill employer_id from the user's Self when the user only has
        // one employer and the field is missing — matches the store path's
        // "no picker shown when there's only Self" UX.
        if (! $this->filled('employer_id')) {
            $employers = $this->user()->employers;
            if ($employers->count() === 1) {
                $this->merge(['employer_id' => $employers->first()->id]);
            }
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
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
