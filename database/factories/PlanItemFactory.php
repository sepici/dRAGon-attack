<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanItem>
 */
class PlanItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plan_period_id' => PlanPeriod::factory(),
            'deliverable_id' => Deliverable::factory(),
            'allocated_hours' => 8.0,
            'status' => Status::Red,
        ];
    }
}
