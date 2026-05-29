<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'employer_id',
        'legal_name',
        'email',
        'phone',
        'notes',
    ];

    /**
     * Application-layer enforcement that employer_id is never null after M13a.
     * The column stays nullable in the schema (to sidestep the doctrine/dbal
     * requirement for column modifications); this observer defaults to the
     * owner's Self employer when the caller hasn't specified one. The web
     * form (M13b) still enforces explicit selection in the UX layer; this
     * fallback keeps API + service paths working transparently.
     */
    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            if (! is_null($client->employer_id)) {
                return;
            }
            if ($client->owner_id) {
                $owner = User::find($client->owner_id);
                if ($owner) {
                    $client->employer_id = $owner->selfEmployer()->id;
                    return;
                }
            }
            throw new \LogicException(
                'Client::employer_id could not be determined: no value set and no owner to derive Self from.'
            );
        });
    }

    // ---------- Relationships ----------------------------------------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function contactPersons(): HasMany
    {
        return $this->hasMany(ContactPerson::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
