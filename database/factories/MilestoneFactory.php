<?php

namespace Database\Factories;

use App\Enums\Moscow;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Milestone>
 */
class MilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement([
                'Discovery', 'Build', 'Test', 'Ship', 'Hand-off',
                'Phase 1', 'Phase 2', 'Backend holding', 'Frontend polish',
            ]),
            'description' => fake()->optional()->paragraph(),
            'target_hours' => fake()->optional(0.5)->randomElement([16, 24, 40, 80, 160]),
            'deadline' => fake()->optional(0.6)->dateTimeBetween('+1 week', '+3 months'),
            'moscow' => fake()->optional(0.7)->randomElement([Moscow::Must, Moscow::Should]),
            'scope_complete' => false,
            'sort_order' => 0,
        ];
    }

    /** Confirm the milestone's scope is final. */
    public function scopeConfirmed(): static
    {
        return $this->state(fn () => ['scope_complete' => true]);
    }
}
