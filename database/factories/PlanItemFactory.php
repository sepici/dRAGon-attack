<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanItem>
 */
class PlanItemFactory extends Factory
{
    /** Default: a deliverable-level allocation. */
    public function definition(): array
    {
        return [
            'plan_period_id' => PlanPeriod::factory(),
            'deliverable_id' => Deliverable::factory(),
            'milestone_id' => null,
            'allocated_hours' => 8.0,
            'status' => Status::Red,
        ];
    }

    /**
     * Milestone-level allocation — for forward planning when you don't yet
     * know which specific deliverables you'll touch.
     */
    public function forMilestone(?Milestone $milestone = null): static
    {
        return $this->state(fn () => [
            'deliverable_id' => null,
            'milestone_id' => $milestone?->id ?? Milestone::factory(),
        ]);
    }
}
