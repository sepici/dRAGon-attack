<?php

namespace App\Models;

use App\Enums\Moscow;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'client_id',
        'name',
        'description',
        'deadline',
        'responsible_contact_id',
        'moscow',
    ];

    protected $casts = [
        'deadline' => 'date',
        'moscow' => Moscow::class,
    ];

    /**
     * Make the computed `status` show up when the model is serialised
     * (e.g. JSON responses, future API endpoints).
     */
    protected $appends = ['status'];

    // ---------- Computed status -------------------------------------------
    // Project status = worst of its deliverables' statuses, per Andrew's
    // framing (R > B > A > G). Stored nowhere — derived on read.

    public function getStatusAttribute(): Status
    {
        return Status::rollup(
            $this->relationLoaded('deliverables')
                ? $this->deliverables->pluck('status')
                : $this->deliverables()->pluck('status')
        );
    }

    // ---------- Relationships ----------------------------------------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsibleContact(): BelongsTo
    {
        return $this->belongsTo(ContactPerson::class, 'responsible_contact_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class);
    }
}
