<?php

namespace App\Models;

use App\Enums\Status;
use App\Support\TimeUnits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deliverable's allocation inside a plan period (weekly/monthly/quarterly).
 *
 * Ad-hoc plan_items were dropped in M8c — unplanned work now lives directly
 * in time_logs (with deliverable_id NULL + ad_hoc_name). plan_items.deliverable_id
 * stays nullable on the schema only to avoid a doctrine/dbal dependency for
 * the ->change() call; application code never inserts null rows.
 */
class PlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_period_id',
        'deliverable_id',
        'allocated_hours',
        'notes',
        'status',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'allocated_hours' => 'decimal:2',
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

    // ---------- Derived hours_spent ---------------------------------------

    /**
     * Hours logged against this plan item's deliverable WITHIN the parent
     * period's [starts_on, ends_on] date window. Derived from time_logs.
     *
     * Hot-path callers should batch-hydrate via
     * PlanPeriod::loadHoursSpent($items) so this lazy fallback never fires.
     */
    public function getHoursSpentAttribute(): float
    {
        if (array_key_exists('hours_spent', $this->attributes)) {
            return (float) $this->attributes['hours_spent'];
        }
        if (is_null($this->deliverable_id)) {
            return 0.0;
        }

        $period = $this->planPeriod;
        if (! $period) {
            return 0.0;
        }

        return (float) TimeLog::query()
            ->where('owner_id', $period->owner_id)
            ->where('deliverable_id', $this->deliverable_id)
            ->whereDate('log_date', '>=', $period->starts_on)
            ->whereDate('log_date', '<=', $period->ends_on)
            ->sum('hours');
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
