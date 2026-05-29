<?php

namespace Database\Factories;

use App\Models\Employer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employer>
 */
class EmployerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'is_self' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * State for an explicit Self employer. Tests rarely need this directly —
     * the User::booted observer creates Self automatically — but having an
     * explicit factory state is handy for migration / scoping tests.
     */
    public function self(): static
    {
        return $this->state(fn () => [
            'name' => 'Self',
            'is_self' => true,
            'sort_order' => 0,
        ]);
    }
}
