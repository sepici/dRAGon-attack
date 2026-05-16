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
            'target_hours' => fake()->randomElement([4, 8, 12, 16, 24, 40]),
            'hours_spent' => 0,
            'deadline' => fake()->optional()->dateTimeBetween('+1 week', '+2 months'),
            'status' => Status::Red,
            'moscow' => fake()->randomElement([Moscow::Must, Moscow::Should, null]),
            'completed_at' => null,
        ];
    }
}
