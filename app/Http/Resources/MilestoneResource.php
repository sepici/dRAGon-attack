<?php

namespace App\Http\Resources;

use App\Support\TimeUnits;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a milestone. Mirrors DeliverableResource's
 * dual-unit (hours + days) convention so agents that prefer either
 * unit don't have to convert.
 *
 * Notable fields specific to milestones:
 *   - target_hours / target_days       MAY be null (= "no manual target")
 *   - effective_target_hours / _days   manual target, or sum of children
 *                                      when manual is null
 *   - status                           derived from child deliverables
 *                                      + scope_complete (see Milestone::derivedStatus)
 *   - scope_ambiguous                  true ⇨ all-Green children but
 *                                      scope_complete is false
 */
class MilestoneResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        $targetHours = is_null($this->target_hours) ? null : (float) $this->target_hours;
        $effectiveTargetHours = (float) $this->effective_target_hours;
        $hoursSpent = (float) $this->hours_spent;

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'target_hours' => $targetHours,
            'target_days' => is_null($targetHours) ? null : TimeUnits::daysFromHours($targetHours),
            'effective_target_hours' => $effectiveTargetHours,
            'effective_target_days' => TimeUnits::daysFromHours($effectiveTargetHours),
            'hours_spent' => $hoursSpent,
            'days_spent' => TimeUnits::daysFromHours($hoursSpent),
            'deadline' => $this->deadline?->toDateString(),
            'moscow' => $this->moscow?->value,
            'scope_complete' => (bool) $this->scope_complete,
            'status' => $this->status->value,
            'scope_ambiguous' => $this->isScopeAmbiguous(),
            'sort_order' => (int) $this->sort_order,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
