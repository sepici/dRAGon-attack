<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'string', 'email', 'max:180',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            // Password is optional on update — blank means "leave as-is"
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'weekly_capacity_hours' => [
                'required', 'numeric', 'min:0', 'max:168',
                'multiple_of:0.5',
            ],
            'monthly_capacity_hours' => [
                'required', 'numeric', 'min:0', 'max:744',
                'multiple_of:0.5',
            ],
        ];
    }
}
