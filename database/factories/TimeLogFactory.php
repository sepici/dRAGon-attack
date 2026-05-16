<?php

namespace Database\Factories;

use App\Models\Deliverable;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeLog>
 */
class TimeLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'log_date' => fake()->dateTimeBetween('-2 weeks', 'today')->format('Y-m-d'),
            'deliverable_id' => Deliverable::factory(),
            'ad_hoc_name' => null,
            // Half-hour increments, typical day chunks.
            'hours' => fake()->randomElement([0.5, 1, 1.5, 2, 2.5, 3, 4, 6, 8]),
            'notes' => null,
        ];
    }

    /** Ad-hoc log (no linked deliverable). */
    public function adHoc(string $name = 'Unplanned work'): static
    {
        return $this->state(fn () => [
            'deliverable_id' => null,
            'ad_hoc_name' => $name,
        ]);
    }

    /** Pin the log to a specific date. */
    public function on(string $date): static
    {
        return $this->state(fn () => ['log_date' => $date]);
    }

    /** Pin the log to a specific user (overrides factory creation). */
    public function for_(User $user): static
    {
        return $this->state(fn () => ['owner_id' => $user->id]);
    }
}
