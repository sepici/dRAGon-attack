<?php

namespace App\Http\Requests;

use App\Enums\Moscow;
use App\Models\ContactPerson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where('owner_id', $this->user()->id),
            ],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
            'responsible_contact_id' => [
                'nullable',
                'exists:contact_persons,id',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }
                    $clientId = $this->input('client_id');
                    if (! $clientId) {
                        return;
                    }
                    $contact = ContactPerson::find($value);
                    if ($contact && (int) $contact->client_id !== (int) $clientId) {
                        $fail('The responsible contact must belong to the selected client.');
                    }
                },
            ],
            'moscow' => ['nullable', Rule::enum(Moscow::class)],
        ];
    }
}
