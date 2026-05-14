<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactPerson extends Model
{
    use HasFactory;

    /**
     * Eloquent would otherwise pluralise this to `contact_people`
     * ("person" → "people"). We named the table `contact_persons` in the
     * migration, so pin it explicitly.
     */
    protected $table = 'contact_persons';

    protected $fillable = [
        'client_id',
        'first_name',
        'last_name',
        'email',
        'role_title',
    ];

    // ---------- Accessors ---------------------------------------------------

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // ---------- Relationships ----------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'deliverable_contacts')
            ->withTimestamps();
    }
}
