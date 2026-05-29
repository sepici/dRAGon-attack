<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\TimeUnits;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'weekly_capacity_hours',
        'monthly_capacity_hours',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'weekly_capacity_hours' => 'decimal:2',
        'monthly_capacity_hours' => 'decimal:2',
    ];

    // ---------- Role helpers ------------------------------------------------
    // One role per user. Use these instead of comparing strings directly.

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isUser(): bool
    {
        return $this->role === UserRole::User;
    }

    public function isViewer(): bool
    {
        return $this->role === UserRole::Viewer;
    }

    // ---------- Lifecycle: auto-create Self on register --------------------
    //
    // Every user gets a "Self" employer the moment their User row is created.
    // Self is always present, always called "Self", never deletable. It's the
    // entity that owns a freelancer's own one-person work.
    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->employers()->firstOrCreate(
                ['is_self' => true],
                ['name' => 'Self', 'sort_order' => 0],
            );
        });
    }

    // ---------- Tracker data relationships ---------------------------------
    // Each user owns their own clients/projects. Deliverables are reached
    // through projects (no direct user FK).

    public function employers(): HasMany
    {
        return $this->hasMany(Employer::class, 'owner_id');
    }

    /**
     * The auto-created Self employer for this user. Always exists once the
     * `created` observer above has fired; we lazily firstOrCreate in case a
     * future test or seeder ever bypasses model events.
     */
    public function selfEmployer(): Employer
    {
        return $this->employers()->firstOrCreate(
            ['is_self' => true],
            ['name' => 'Self', 'sort_order' => 0],
        );
    }

    /**
     * Viewer-style users get read access to specific employers via the
     * employer_viewers join (M13c). For non-viewer users this returns an
     * empty relation.
     */
    public function grantedEmployers(): BelongsToMany
    {
        return $this->belongsToMany(Employer::class, 'employer_viewers', 'viewer_id', 'employer_id')
            ->withTimestamps();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'owner_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function planPeriods(): HasMany
    {
        return $this->hasMany(PlanPeriod::class, 'owner_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'owner_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'owner_id');
    }

    /** All day-by-day work logs this user has entered (cross-deliverable). */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class, 'owner_id');
    }

    // ---------- Capacity helpers (derived days view) -----------------------

    public function getWeeklyCapacityDaysAttribute(): float
    {
        return TimeUnits::daysFromHours($this->weekly_capacity_hours);
    }

    public function getMonthlyCapacityDaysAttribute(): float
    {
        return TimeUnits::daysFromHours($this->monthly_capacity_hours);
    }
}
