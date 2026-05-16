<?php

namespace App\Models;

use App\Support\TimeUnits;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single chunk of work logged on a specific day.
 *
 * Two shapes:
 *   • deliverable-linked: $log->deliverable_id is set, $log->ad_hoc_name is null.
 *   • ad-hoc:             $log->deliverable_id is null, $log->ad_hoc_name is set
 *                         (e.g. "Emergency Cloudways restart").
 *
 * The application layer enforces that exactly one of those is true; the DB
 * schema doesn't (Laravel's migration DSL has no portable CHECK constraint
 * helper, and we don't want to drift between SQLite-in-tests and MySQL-in-prod).
 *
 * Aggregates derived from this table:
 *   • Deliverable::hours_spent — SUM(hours WHERE deliverable_id = X)
 *   • PlanItem::hours_spent    — SUM(hours WHERE deliverable_id = X
 *                                    AND log_date BETWEEN period.starts_on
 *                                                     AND period.ends_on)
 * Both wired up properly in M8c.
 */
class TimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'log_date',
        'deliverable_id',
        'ad_hoc_name',
        'hours',
        'notes',
    ];

    protected $casts = [
        'log_date' => 'date',
        'hours' => 'decimal:2',
    ];

    // ---------- Relationships ----------------------------------------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    // ---------- Scopes ------------------------------------------------------

    /** Logs owned by the given user. */
    public function scopeForOwner(Builder $q, User $user): Builder
    {
        return $q->where('owner_id', $user->id);
    }

    /** Logs whose log_date equals $date. */
    public function scopeForDate(Builder $q, CarbonImmutable|string $date): Builder
    {
        return $q->whereDate('log_date', $date instanceof CarbonImmutable
            ? $date->toDateString()
            : $date);
    }

    /**
     * Logs whose log_date falls in [$start, $end] inclusive.
     *
     * Uses two whereDate() calls rather than whereBetween() so that the
     * comparison is date-only on every driver. SQLite stores DATE columns
     * as strings with a time suffix; a bare whereBetween against
     * 'YYYY-MM-DD' would exclude rows on the upper bound.
     */
    public function scopeInRange(
        Builder $q,
        CarbonImmutable|string $start,
        CarbonImmutable|string $end,
    ): Builder {
        $s = $start instanceof CarbonImmutable ? $start->toDateString() : (string) $start;
        $e = $end instanceof CarbonImmutable ? $end->toDateString() : (string) $end;
        return $q->whereDate('log_date', '>=', $s)
            ->whereDate('log_date', '<=', $e);
    }

    // ---------- Convenience -------------------------------------------------

    public function isAdHoc(): bool
    {
        return is_null($this->deliverable_id);
    }

    /** Display name regardless of shape. */
    public function displayName(): string
    {
        return $this->isAdHoc()
            ? ($this->ad_hoc_name ?? '(unnamed)')
            : ($this->deliverable->name ?? '(missing deliverable)');
    }

    /** Derived days view of the hours value. */
    public function getDaysAttribute(): float
    {
        return TimeUnits::daysFromHours($this->hours);
    }
}
