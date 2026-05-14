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
            'allocated_days' => 1.0,
            'days_spent' => 0,
            'status' => Status::Red,
        ];
    }

    /** Ad-hoc item (no linked deliverable) — used by review tests in M4. */
    public function adHoc(string $name = 'Unplanned work'): static
    {
        return $this->state(fn () => [
            'deliverable_id' => null,
            'ad_hoc_name' => $name,
        ]);
    }
}
