<?php

namespace App\Http\Resources;

use App\Support\TimeUnits;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON shape for a User. Used by /api/v1/me.
 *
 * Includes both the hours columns (storage units) and derived days for
 * convenience — agents asking "how much capacity does this person have?"
 * usually want days.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'capacity' => [
                'weekly_hours' => (float) $this->weekly_capacity_hours,
                'weekly_days' => TimeUnits::daysFromHours($this->weekly_capacity_hours),
                'monthly_hours' => (float) $this->monthly_capacity_hours,
                'monthly_days' => TimeUnits::daysFromHours($this->monthly_capacity_hours),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
