<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Parent client must be updatable by the current user
        // (managing contacts == updating the client's contact list).
        return $this->user()->can('update', $this->route('client'));
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:180'],
            'role_title' => ['nullable', 'string', 'max:120'],
        ];
    }
}
