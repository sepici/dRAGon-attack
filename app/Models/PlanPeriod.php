<?php

namespace App\Models;

use App\Enums\PlanKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'kind',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'kind' => PlanKind::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    // ---------- Calendar arithmetic ---------------------------------------

    /**
     * The (start, end) date pair for the current period of the given kind,
     * anchored to today. Calendar-aligned:
     *   weekly    — Monday → Sunday of the current week
     *   monthly   — 1st → last of the current calendar month
     *   quarterly — 1st of current month → last of (current month + 2)
     *
     * Returns [CarbonImmutable $startsOn, CarbonImmutable $endsOn].
     */
    public static function boundsFor(PlanKind $kind, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return match ($kind) {
            PlanKind::Weekly => [
                $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay(),
                $now->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay(),
            ],
            PlanKind::Monthly => [
                $now->startOfMonth()->startOfDay(),
                $now->endOfMonth()->startOfDay(),
            ],
            PlanKind::Quarterly => [
                $now->startOfMonth()->startOfDay(),
                $now->startOfMonth()->addMonthsNoOverflow(2)->endOfMonth()->startOfDay(),
            ],
        };
    }

    /**
     * Find or create the current period of $kind for $user. Idempotent —
     * once a period exists for that (owner, kind, starts_on) it's reused.
     */
    public static function findOrCreateCurrentFor(User $user, PlanKind $kind): self
    {
        [$start, $end] = self::boundsFor($kind);

        return self::findOrCreateForOwner($user->id, $kind, $start, $end);
    }

    /**
     * Lookup-or-create helper that's robust against the Eloquent date-cast
     * quirk: `firstOrCreate` writes values via casts (so a 'date' column
     * may be stored as 'YYYY-MM-DD HH:mm:ss' in some adapters) but matches
     * the WHERE clause against the raw input ('YYYY-MM-DD'), causing the
     * second call to miss the row and try to re-insert.
     *
     * We do an explicit `whereDate` lookup first, then a plain `create`.
     */
    public static function findOrCreateForOwner(
        int $ownerId,
        PlanKind $kind,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
    ): self {
        $existing = self::query()
            ->where('owner_id', $ownerId)
            ->where('kind', $kind->value)
            ->whereDate('starts_on', $startsOn->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        return self::create([
            'owner_id' => $ownerId,
            'kind' => $kind->value,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
        ]);
    }

    // ---------- Relationships ----------------------------------------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlanItem::class)->orderBy('sort_order')->orderBy('id');
    }

    // ---------- Aggregates (all in hours) -----------------------------------

    /** Sum of allocated_hours across all items in this period. */
    public function totalAllocated(): float
    {
        return (float) $this->items()->sum('allocated_hours');
    }

    /**
     * Sum of every time_log this owner recorded inside the period's
     * [starts_on, ends_on] window — including ad-hoc logs that aren't
     * tied to a plan_item. Used by the PDF report's "spent this week"
     * summary line.
     */
    public function totalSpent(): float
    {
        return (float) TimeLog::query()
            ->where('owner_id', $this->owner_id)
            ->whereDate('log_date', '>=', $this->starts_on)
            ->whereDate('log_date', '<=', $this->ends_on)
            ->sum('hours');
    }

    /** The user's capacity for THIS kind of period (in hours). */
    public function capacity(): float
    {
        return match ($this->kind) {
            PlanKind::Weekly => (float) $this->owner->weekly_capacity_hours,
            PlanKind::Monthly => (float) $this->owner->monthly_capacity_hours,
            // Quarterly capacity ≈ 3 × monthly capacity (no separate column).
            PlanKind::Quarterly => 3.0 * (float) $this->owner->monthly_capacity_hours,
        };
    }

    /** Difference: planned − capacity, in hours. Positive = over-committed. */
    public function overUnder(): float
    {
        return $this->totalAllocated() - $this->capacity();
    }

    /**
     * Hydrate each plan_item's derived hours_spent attribute with batched
     * SUMs over time_logs. Two shapes share this period:
     *
     *   • Deliverable allocations  → SUM(time_logs.hours WHERE deliverable_id IN …)
     *   • Milestone  allocations   → SUM of all logs across that milestone's
     *                                child deliverables in the period window
     *
     * Both buckets are hydrated in a single query each (no N+1).
     *
     * Returns the period itself so callers can chain.
     */
    public function loadHoursSpent(): self
    {
        $items = $this->items;
        if ($items->isEmpty()) {
            return $this;
        }

        // Default every item to 0 so callers can rely on the attribute
        // being set even when no logs match.
        $items->each(fn ($i) => $i->setAttribute('hours_spent', 0.0));

        // --- Deliverable-type items ---------------------------------------
        $deliverableIds = $items->pluck('deliverable_id')->filter()->unique();
        if ($deliverableIds->isNotEmpty()) {
            $delivSums = TimeLog::query()
                ->where('owner_id', $this->owner_id)
                ->whereDate('log_date', '>=', $this->starts_on)
                ->whereDate('log_date', '<=', $this->ends_on)
                ->whereIn('deliverable_id', $deliverableIds)
                ->selectRaw('deliverable_id, SUM(hours) as total')
                ->groupBy('deliverable_id')
                ->pluck('total', 'deliverable_id');

            $items->each(function ($item) use ($delivSums) {
                if ($item->deliverable_id) {
                    $item->setAttribute('hours_spent', (float) ($delivSums[$item->deliverable_id] ?? 0));
                }
            });
        }

        // --- Milestone-type items -----------------------------------------
        // Sum logs grouped by deliverable.milestone_id within the period.
        $milestoneIds = $items->pluck('milestone_id')->filter()->unique();
        if ($milestoneIds->isNotEmpty()) {
            $mileSums = TimeLog::query()
                ->where('time_logs.owner_id', $this->owner_id)
                ->whereDate('time_logs.log_date', '>=', $this->starts_on)
                ->whereDate('time_logs.log_date', '<=', $this->ends_on)
                ->join('deliverables', 'deliverables.id', '=', 'time_logs.deliverable_id')
                ->whereIn('deliverables.milestone_id', $milestoneIds)
                ->selectRaw('deliverables.milestone_id as mid, SUM(time_logs.hours) as total')
                ->groupBy('deliverables.milestone_id')
                ->pluck('total', 'mid');

            $items->each(function ($item) use ($mileSums) {
                if ($item->milestone_id) {
                    $item->setAttribute('hours_spent', (float) ($mileSums[$item->milestone_id] ?? 0));
                }
            });
        }

        return $this;
    }
}
