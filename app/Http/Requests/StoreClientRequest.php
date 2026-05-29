<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Client::class);
    }

    /**
     * If the user has only the Self employer (no others added), they don't
     * see the picker on the form — auto-fill employer_id to Self here so the
     * UI stays clean and the validation rule below still holds.
     */
    protected function prepareForValidation(): void
    {
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
