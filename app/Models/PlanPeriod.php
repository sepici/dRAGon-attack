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

        return self::firstOrCreate(
            [
                'owner_id' => $user->id,
                'kind' => $kind,
                'starts_on' => $start->toDateString(),
            ],
            [
                'ends_on' => $end->toDateString(),
            ],
        );
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

    // ---------- Aggregates --------------------------------------------------

    /** Sum of allocated_days across all items in this period. */
    public function totalAllocated(): float
    {
        return (float) $this->items()->sum('allocated_days');
    }

    /** The user's capacity for THIS kind of period (from their User profile). */
    public function capacity(): float
    {
        return match ($this->kind) {
            PlanKind::Weekly => (float) $this->owner->weekly_capacity_days,
            PlanKind::Monthly => (float) $this->owner->monthly_capacity_days,
            // Quarterly capacity ≈ 3 × monthly capacity (no separate column).
            PlanKind::Quarterly => 3.0 * (float) $this->owner->monthly_capacity_days,
        };
    }

    /** Difference: planned − capacity. Positive = over-committed. */
    public function overUnder(): float
    {
        return $this->totalAllocated() - $this->capacity();
    }
}
