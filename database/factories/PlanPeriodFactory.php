<?php

namespace Database\Factories;

use App\Enums\PlanKind;
use App\Models\PlanPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanPeriod>
 */
class PlanPeriodFactory extends Factory
{
    public function definition(): array
    {
        // Default to the current week. Tests can override via state helpers.
        [$start, $end] = PlanPeriod::boundsFor(PlanKind::Weekly);

        return [
            'owner_id' => User::factory(),
            'kind' => PlanKind::Weekly,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
        ];
    }

    public function weekly(): static
    {
        [$start, $end] = PlanPeriod::boundsFor(PlanKind::Weekly);
        return $this->state(fn () => [
            'kind' => PlanKind::Weekly,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
        ]);
    }

    public function monthly(): static
    {
        [$start, $end] = PlanPeriod::boundsFor(PlanKind::Monthly);
        return $this->state(fn () => [
            'kind' => PlanKind::Monthly,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
        ]);
    }

    public function quarterly(): static
    {
        [$start, $end] = PlanPeriod::boundsFor(PlanKind::Quarterly);
        return $this->state(fn () => [
            'kind' => PlanKind::Quarterly,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
        ]);
    }
}
