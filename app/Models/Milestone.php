<?php

namespace App\Models;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Support\TimeUnits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A milestone is a phase / chunk of work within a project. See the
 * 2026_05_26 migration for the design rationale.
 *
 * Notable accessors:
 *   $milestone->status              derived RAGB from children + scope_complete
 *   $milestone->hours_spent         derived sum of children time_logs
 *   $milestone->effective_target_hours  the manual target, or sum of
 *                                       children when manual is null
 */
class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'target_hours',
        'deadline',
        'moscow',
        'scope_complete',
        'sort_order',
    ];

    protected $casts = [
        'target_hours' => 'decimal:2',
        'deadline' => 'date',
        'moscow' => Moscow::class,
        'scope_complete' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // App-level cleanup so behaviour is consistent regardless of the DB.
        // SQLite (used in tests) doesn't enforce FK constraints added via
        // ALTER TABLE; MySQL does. We do the work here so both behave the
        // same — on MySQL the DB cascade then becomes a no-op.
        static::deleting(function (Milestone $milestone) {
            $milestone->deliverables()->update(['milestone_id' => null]);
            $milestone->planItems()->delete();
        });
    }

    // ---------- Relationships ----------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class);
    }

    public function planItems(): HasMany
    {
        return $this->hasMany(PlanItem::class);
    }

    // ---------- Derived status --------------------------------------------

    /**
     * Status is derived from child deliverables + scope_complete. There is
     * NO `status` column on the table — call this method (or the accessor
     * below) to get the current value.
     *
     * Rules:
     *   - any child Red    → Red
     *   - any child Blocked→ Blocked
     *   - any child Amber  → Amber
     *   - all children Green:
     *       scope_complete = true  → Green
     *       scope_complete = false → Amber (with a "scope not confirmed"
     *                                       caveat shown in the UI)
     *   - no children → Red (the milestone is unstarted scope)
     */
    public function derivedStatus(): Status
    {
        // Use the loaded relation when possible to avoid an extra query
        // inside loops that already eager-loaded deliverables.
        $children = $this->relationLoaded('deliverables')
            ? $this->deliverables
            : $this->deliverables()->get();

        if ($children->isEmpty()) {
            return Status::Red;
        }

        $hasRed = false;
        $hasBlocked = false;
        $hasAmber = false;
        $allGreen = true;

        foreach ($children as $d) {
            $s = $d->status;
            if ($s === Status::Red) {
                $hasRed = true;
                $allGreen = false;
            } elseif ($s === Status::Blocked) {
                $hasBlocked = true;
                $allGreen = false;
            } elseif ($s === Status::Amber) {
                $hasAmber = true;
                $allGreen = false;
            }
        }

        if ($hasRed) {
            return Status::Red;
        }
        if ($hasBlocked) {
            return Status::Blocked;
        }
        if ($hasAmber) {
            return Status::Amber;
        }
        // All children Green at this point.
        return $this->scope_complete ? Status::Green : Status::Amber;
    }

    /** Accessor so `$milestone->status` reads like a normal property. */
    public function getStatusAttribute(): Status
    {
        return $this->derivedStatus();
    }

    /**
     * Flag for the UI: this milestone's children are all-Green but the user
     * hasn't confirmed scope. The status accessor returns Amber in this
     * case; this flag tells the UI to show the caveat badge.
     */
    public function isScopeAmbiguous(): bool
    {
        if ($this->scope_complete) {
            return false;
        }
        $children = $this->relationLoaded('deliverables')
            ? $this->deliverables
            : $this->deliverables()->get();
        if ($children->isEmpty()) {
            return false;
        }
        return $children->every(fn ($d) => $d->status === Status::Green);
    }

    // ---------- Derived hours ---------------------------------------------

    /**
     * Sum of hours logged against any deliverable in this milestone, across
     * all time. Mirrors Deliverable::hours_spent semantics.
     */
    public function getHoursSpentAttribute(): float
    {
        if (array_key_exists('hours_spent', $this->attributes)) {
            return (float) $this->attributes['hours_spent'];
        }
        return (float) TimeLog::query()
            ->whereIn(
                'deliverable_id',
                $this->deliverables()->select('id'),
            )
            ->sum('hours');
    }

    /**
     * Use the manual target when set; otherwise sum children's target_hours.
     * Useful for displays that show "what should this milestone cost?"
     * before all deliverables are scoped.
     */
    public function getEffectiveTargetHoursAttribute(): float
    {
        if (! is_null($this->target_hours)) {
            return (float) $this->target_hours;
        }
        return (float) $this->deliverables()->sum('target_hours');
    }

    /** Derived days view of hours_spent. */
    public function getDaysSpentAttribute(): float
    {
        return TimeUnits::daysFromHours($this->hours_spent);
    }

    /** Derived days view of effective_target_hours. */
    public function getEffectiveTargetDaysAttribute(): float
    {
        return TimeUnits::daysFromHours($this->effective_target_hours);
    }
}
