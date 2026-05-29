<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Employer is the entity a User does work FOR. A freelancer might have
 * "Acme Co.", "Globex" plus the auto-created "Self" employer that covers
 * their own one-person work. Self is always present, never deletable.
 *
 * The Employer sits above Client in the ownership chain:
 *
 *     User → Employer → Client → Project → (Milestone) → Deliverable → TimeLog
 *
 * Viewer accounts are granted read access on a per-employer basis (M13c),
 * so the employer is the boundary for read-permission slicing.
 */
class Employer extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'is_self',
        'sort_order',
    ];

    protected $casts = [
        'is_self' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // Hard guards. The web/API controllers do their own friendlier-check
        // first; these are the last line of defence catching factories,
        // tinker, seeders, and anything else that bypasses the UI.
        static::deleting(function (Employer $employer) {
            if ($employer->is_self) {
                throw new \LogicException(
                    'The Self employer cannot be deleted.'
                );
            }
            if ($employer->clients()->exists()) {
                throw new \LogicException(sprintf(
                    'Employer "%s" still has %d client(s); move or delete them first.',
                    $employer->name,
                    $employer->clients()->count(),
                ));
            }
        });

        // The Self employer's name is fixed at "Self" — refuse renames at the
        // model level. Other guard rails (the UI hides the field, the
        // FormRequest rejects mismatches) make this a belt-and-braces check.
        static::updating(function (Employer $employer) {
            if ($employer->is_self && $employer->isDirty('name') && $employer->name !== 'Self') {
                throw new \LogicException(
                    'The Self employer\'s name is fixed at "Self".'
                );
            }
            if ($employer->isDirty('is_self')) {
                throw new \LogicException(
                    'is_self is set at creation and cannot be toggled.'
                );
            }
        });
    }

    // ---------- Relationships ----------------------------------------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Viewer-style users that have been granted read access to this employer.
     * Many-to-many via the employer_viewers join table (M13c).
     */
    public function viewers()
    {
        return $this->belongsToMany(User::class, 'employer_viewers', 'employer_id', 'viewer_id')
            ->withTimestamps();
    }
}
