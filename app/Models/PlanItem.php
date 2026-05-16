<?php

namespace App\Models;

use App\Enums\Status;
use App\Support\TimeUnits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_period_id',
        'deliverable_id',
        'ad_hoc_name',
        'ad_hoc_notes',
        'allocated_hours',
        'hours_spent',
        'notes',
        'status',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'allocated_hours' => 'decimal:2',
        'hours_spent' => 'decimal:2',
        'status' => Status::class,
        'completed_at' => 'datetime',
    ];

    // ---------- Relationships ----------------------------------------------

    public function planPeriod(): BelongsTo
    {
        return $this->belongsTo(PlanPeriod::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    // ---------- Convenience -------------------------------------------------

    public function isAdHoc(): bool
    {
        return is_null($this->deliverable_id);
    }

    public function displayName(): string
    {
        return $this->isAdHoc()
            ? ($this->ad_hoc_name ?? '(unnamed)')
            : ($this->deliverable->name ?? '(missing deliverable)');
    }

    /** Derived days view of allocated_hours. */
    public function getAllocatedDaysAttribute(): float
    {
        return TimeUnits::daysFromHours($this->allocated_hours);
    }

    /** Derived days view of hours_spent. */
    public function getDaysSpentAttribute(): float
    {
        return TimeUnits::daysFromHours($this->hours_spent);
    }
}
