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
        'status',
        'moscow',
    ];

    protected $casts = [
        'deadline' => 'date',
        'status' => Status::class,
        'moscow' => Moscow::class,
    ];

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
