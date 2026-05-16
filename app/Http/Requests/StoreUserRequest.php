<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Support\TimeUnits;
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

    /**
     * Capacity is entered in working *days* per period (one workday = 8h).
     * The DB stores hours; we convert before validation.
     */
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            // Days inputs (the form fields). Half-day increments.
            'weekly_capacity_days' => ['required', 'numeric', 'min:0', 'max:7', 'multiple_of:0.5'],
            'monthly_capacity_days' => ['required', 'numeric', 'min:0', 'max:31', 'multiple_of:0.5'],
            // Hours columns (derived). Re-validate so they end up in validated() output.
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
