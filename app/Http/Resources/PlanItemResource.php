<?php

namespace App\Http\Resources;

use App\Support\TimeUnits;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanItemResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        $allocated = (float) $this->allocated_hours;
        $spent = (float) $this->hours_spent;

        return [
            'id' => $this->id,
            'plan_period_id' => $this->plan_period_id,
            // Exactly one of deliverable_id / milestone_id is non-null. The
            // accessor returns null for the unset side so callers can
            // discriminate without parsing.
            'deliverable_id' => $this->deliverable_id,
            'milestone_id' => $this->milestone_id,
            'allocated_hours' => $allocated,
            'allocated_days' => TimeUnits::daysFromHours($allocated),
            // hours_spent here is period-scoped: only counts time_logs whose
            // log_date falls within the parent plan_period's window. For
            // milestone allocations this rolls up across child deliverables.
            'hours_spent' => $spent,
            'days_spent' => TimeUnits::daysFromHours($spent),
            'notes' => $this->notes,
            'status' => $this->status?->value,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'deliverable' => new DeliverableResource($this->whenLoaded('deliverable')),
            'milestone' => new MilestoneResource($this->whenLoaded('milestone')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
