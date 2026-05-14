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
