<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One row per "share this employer's tracker with X" invitation. See the
 * viewer_invitations migration for the full lifecycle.
 */
class ViewerInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'inviter_id',
        'email',
        'name',
        'token',
        'employer_ids',
        'message',
        'expires_at',
        'accepted_at',
        'viewer_id',
    ];

    protected $casts = [
        'employer_ids' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Generate a fresh random token on first save when caller didn't supply
     * one. Sized for unguessable URLs without being awkward to type or paste.
     */
    protected static function booted(): void
    {
        static::creating(function (ViewerInvitation $inv) {
            if (empty($inv->token)) {
                $inv->token = Str::random(48);
            }
        });
    }

    // ---------- Relationships ----------------------------------------------

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    // ---------- Convenience -----------------------------------------------

    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }
}
