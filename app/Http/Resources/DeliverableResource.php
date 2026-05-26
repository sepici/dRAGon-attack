<?php

namespace App\Http\Resources;

use App\Support\TimeUnits;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Includes both hours (storage) and derived days for every duration field,
 * so agents that prefer either unit don't have to convert.
 */
class DeliverableResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        $hoursSpent = (float) $this->hours_spent;
        $targetHours = (float) $this->target_hours;
        $remaining = max(0.0, $targetHours - $hoursSpent);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'target_hours' => $targetHours,
            'target_days' => TimeUnits::daysFromHours($targetHours),
            'hours_spent' => $hoursSpent,
            'days_spent' => TimeUnits::daysFromHours($hoursSpent),
            'remaining_hours' => $remaining,
            'remaining_days' => TimeUnits::daysFromHours($remaining),
            'deadline' => $this->deadline?->toDateString(),
            'status' => $this->status?->value,
            'moscow' => $this->moscow?->value,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'milestone_id' => $this->milestone_id,
            'milestone' => new MilestoneResource($this->whenLoaded('milestone')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
