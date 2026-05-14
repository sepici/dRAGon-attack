<?php

namespace Database\Factories;

use App\Enums\Moscow;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $owner = User::factory();

        return [
            'owner_id' => $owner,
            // Create a client owned by the same user
            'client_id' => Client::factory()->state(fn () => ['owner_id' => $owner]),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'deadline' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
            'responsible_contact_id' => null,
            'moscow' => fake()->randomElement([Moscow::Must, Moscow::Should, null]),
        ];
    }
}
