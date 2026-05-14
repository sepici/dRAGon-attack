<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Authorisation is handled at the route level via the role:admin
     * middleware, so any request that gets here is allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'weekly_capacity_days' => [
                'required', 'numeric', 'min:0', 'max:7',
                'multiple_of:0.5',
            ],
            'monthly_capacity_days' => [
                'required', 'numeric', 'min:0', 'max:31',
                'multiple_of:0.5',
            ],
        ];
    }
}
