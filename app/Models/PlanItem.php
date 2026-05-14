<?php

namespace App\Models;

use App\Enums\Status;
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
        'allocated_days',
        'days_spent',
        'notes',
        'status',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'allocated_days' => 'decimal:1',
        'days_spent' => 'decimal:1',
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

    /** True when this row represents unplanned work (no deliverable link). */
    public function isAdHoc(): bool
    {
        return is_null($this->deliverable_id);
    }

    /** Display name — deliverable's name or the ad-hoc label. */
    public function displayName(): string
    {
        return $this->isAdHoc()
            ? ($this->ad_hoc_name ?? '(unnamed)')
            : ($this->deliverable->name ?? '(missing deliverable)');
    }
}
