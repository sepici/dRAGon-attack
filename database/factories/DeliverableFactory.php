<?php

namespace Database\Factories;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deliverable>
 */
class DeliverableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'target_days' => fake()->randomElement([0.5, 1, 1.5, 2, 3, 5]),
            'days_spent' => 0,
            'deadline' => fake()->optional()->dateTimeBetween('+1 week', '+2 months'),
            'status' => Status::Red,
            'moscow' => fake()->randomElement([Moscow::Must, Moscow::Should, null]),
            'completed_at' => null,
        ];
    }
}
