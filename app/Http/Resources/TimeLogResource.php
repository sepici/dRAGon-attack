<?php

namespace App\Http\Resources;

use App\Support\TimeUnits;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeLogResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        $hours = (float) $this->hours;

        return [
            'id' => $this->id,
            'log_date' => $this->log_date?->toDateString(),
            'hours' => $hours,
            'days' => TimeUnits::daysFromHours($hours),
            'notes' => $this->notes,
            'deliverable_id' => $this->deliverable_id,
            'ad_hoc_name' => $this->ad_hoc_name,
            'is_ad_hoc' => is_null($this->deliverable_id),
            'deliverable' => new DeliverableResource($this->whenLoaded('deliverable')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
