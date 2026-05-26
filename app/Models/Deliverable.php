<?php

namespace App\Models;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Support\TimeUnits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deliverable extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'milestone_id',
        'name',
        'description',
        'target_hours',
        'deadline',
        'status',
        'moscow',
        'completed_at',
    ];

    protected $casts = [
        'target_hours' => 'decimal:2',
        'deadline' => 'date',
        'status' => Status::class,
        'moscow' => Moscow::class,
        'completed_at' => 'datetime',
    ];

    // ---------- Relationships ----------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Optional grouping within the project. A deliverable's milestone must
     * belong to the same project (enforced at the FormRequest level).
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * Many-to-many with contact_persons. A deliverable inherits one
     * responsible contact from its project; the user can attach more
     * contacts from the same client via this relationship.
     */
    public function contactPersons(): BelongsToMany
    {
        return $this->belongsToMany(ContactPerson::class, 'deliverable_contacts')
            ->withTimestamps();
    }

    /** All plan_items (weekly/monthly/quarterly allocations) of this deliverable. */
    public function planItems(): HasMany
    {
        return $this->hasMany(PlanItem::class);
    }

    /** Every day-by-day log of work against this deliverable. */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    // ---------- Convenience accessors --------------------------------------

    /** True when this deliverable has been signed off (completed_at set). */
    public function isComplete(): bool
    {
        return ! is_null($this->completed_at);
    }

    /**
     * Cumulative hours logged against this deliverable, derived from
     * time_logs. Controllers that render lists should call
     * ->withHoursSpent() to eager-load via a single SUM; the lazy fallback
     * here only kicks in when the attribute isn't already populated.
     */
    public function getHoursSpentAttribute(): float
    {
        if (array_key_exists('hours_spent', $this->attributes)) {
            return (float) $this->attributes['hours_spent'];
        }
        return (float) $this->timeLogs()->sum('hours');
    }

    /**
     * Eager-load hours_spent as a SUM(time_logs.hours) column on the result.
     *   Deliverable::withHoursSpent()->get();
     */
    public function scopeWithHoursSpent($query)
    {
        return $query->withSum('timeLogs as hours_spent', 'hours');
    }

    /** Remaining hours = target − spent, never negative. */
    public function getRemainingHoursAttribute(): float
    {
        return max(0.0, (float) $this->target_hours - (float) $this->hours_spent);
    }

    /** Derived days view of target_hours. */
    public function getTargetDaysAttribute(): float
    {
        return TimeUnits::daysFromHours($this->target_hours);
    }

    /** Derived days view of hours_spent. */
    public function getDaysSpentAttribute(): float
    {
        return TimeUnits::daysFromHours($this->hours_spent);
    }
}
