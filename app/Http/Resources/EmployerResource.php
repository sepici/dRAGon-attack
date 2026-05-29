<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REST representation of an Employer. Includes a couple of cheap counts
 * the agent might want (clients_count) so it doesn't need to spelunk
 * through nested routes for basic summaries.
 */
class EmployerResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_self' => (bool) $this->is_self,
            'sort_order' => (int) $this->sort_order,
            'clients_count' => $this->whenCounted('clients'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
