<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Support\TimeUnits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** See StoreUserRequest::prepareForValidation. */
    protected function prepareForValidation(): void
    {
        if ($this->has('weekly_capacity_days') && $this->input('weekly_capacity_days') !== '') {
            $this->merge([
                'weekly_capacity_hours' => TimeUnits::hoursFromDays(
                    (float) $this->input('weekly_capacity_days'),
                ),
            ]);
        }
        if ($this->has('monthly_capacity_days') && $this->input('monthly_capacity_days') !== '') {
            $this->merge([
                'monthly_capacity_hours' => TimeUnits::hoursFromDays(
                    (float) $this->input('monthly_capacity_days'),
                ),
            ]);
        }
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
            'weekly_capacity_days' => ['required', 'numeric', 'min:0', 'max:7', 'multiple_of:0.5'],
            'monthly_capacity_days' => ['required', 'numeric', 'min:0', 'max:31', 'multiple_of:0.5'],
            'weekly_capacity_hours' => ['required', 'numeric', 'min:0', 'max:168'],
            'monthly_capacity_hours' => ['required', 'numeric', 'min:0', 'max:744'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            unset($data['weekly_capacity_days'], $data['monthly_capacity_days']);
        }
        return $data;
    }
}
