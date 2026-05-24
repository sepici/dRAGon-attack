<?php

namespace App\Http\Resources;

use App\Support\TimeUnits;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanPeriodResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        // Allocation totals come from in-memory aggregates (controller hydrates).
        $allocated = (float) $this->totalAllocated();
        $capacity = (float) $this->capacity();
        $spent = (float) $this->totalSpent();
        $overUnder = $allocated - $capacity;

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'capacity_hours' => $capacity,
            'capacity_days' => TimeUnits::daysFromHours($capacity),
            'allocated_hours' => $allocated,
            'allocated_days' => TimeUnits::daysFromHours($allocated),
            'spent_hours' => $spent,
            'spent_days' => TimeUnits::daysFromHours($spent),
            'over_under_hours' => $overUnder,
            'over_under_days' => TimeUnits::daysFromHours($overUnder),
            'items' => PlanItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
