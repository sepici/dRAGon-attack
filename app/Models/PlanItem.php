<?php

namespace App\Models;

use App\Enums\Status;
use App\Support\TimeUnits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An allocation inside a plan period. After M12a a plan_item targets
 * EITHER a deliverable OR a milestone — exactly one of the two foreign
 * keys is set. The deliverable form is the existing "1.5 days on this
 * specific item" allocation; the milestone form is the
 * "5 days on this chunk somewhere" forward-planning envelope.
 *
 * The exactly-one invariant is enforced at the application layer via a
 * saving observer (no portable SQL CHECK constraint).
 *
 * Ad-hoc plan_items were dropped in M8c — unplanned work lives in time_logs.
 */
class PlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_period_id',
        'deliverable_id',
        'milestone_id',
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

    protected static function booted(): void
    {
        // Enforce the exactly-one-of invariant on save. This catches factory
        // misuse, tinker calls, future seeders — any path that bypasses the
        // FormRequest. Throws instead of silently corrupting data.
        static::saving(function (PlanItem $item) {
            $hasDeliv = ! is_null($item->deliverable_id);
            $hasMile = ! is_null($item->milestone_id);
            if ($hasDeliv === $hasMile) {
                throw new \LogicException(sprintf(
                    'PlanItem must have exactly one of deliverable_id or milestone_id (got deliverable_id=%s, milestone_id=%s).',
                    var_export($item->deliverable_id, true),
                    var_export($item->milestone_id, true),
                ));
            }
        });
    }

    // ---------- Relationships ----------------------------------------------

    public function planPeriod(): BelongsTo
    {
        return $this->belongsTo(PlanPeriod::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    // ---------- Shape helpers ---------------------------------------------

    public function isDeliverableAllocation(): bool
    {
        return ! is_null($this->deliverable_id);
    }

    public function isMilestoneAllocation(): bool
    {
        return ! is_null($this->milestone_id);
    }

    // ---------- Derived hours_spent ---------------------------------------

    /**
     * Hours logged against this plan item's target WITHIN the parent
     * period's [starts_on, ends_on] date window. Derived from time_logs.
     *
     * For deliverable allocations: SUM of time_logs on that one deliverable.
     * For milestone allocations: SUM of time_logs on any deliverable that
     *   belongs to that milestone. Note: this double-counts with any
     *   deliverable-level plan items under the same milestone in the same
     *   period — see the M11 design rationale (the milestone is the
     *   envelope, the deliverables are specific draws against it).
     *
     * Hot-path callers should batch-hydrate via PlanPeriod::loadHoursSpent
     * so this lazy fallback never fires.
     */
    public function getHoursSpentAttribute(): float
    {
        if (array_key_exists('hours_spent', $this->attributes)) {
            return (float) $this->attributes['hours_spent'];
        }

        $period = $this->planPeriod;
        if (! $period) {
            return 0.0;
        }

        $query = TimeLog::query()
            ->where('owner_id', $period->owner_id)
            ->whereDate('log_date', '>=', $period->starts_on)
            ->whereDate('log_date', '<=', $period->ends_on);

        if ($this->deliverable_id) {
            $query->where('deliverable_id', $this->deliverable_id);
        } elseif ($this->milestone_id) {
            $query->whereIn(
                'deliverable_id',
                Deliverable::query()
                    ->where('milestone_id', $this->milestone_id)
                    ->select('id'),
            );
        } else {
            return 0.0;
        }

        return (float) $query->sum('hours');
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
