<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Generated monthly timesheet PDF. Shape mirrors Report.
 */
class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'month_starts_on',
        'generated_at',
        'file_path',
    ];

    protected $casts = [
        'month_starts_on' => 'date',
        'generated_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Absolute path on disk. */
    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    public function fileExists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }

    /** Display-friendly filename for Content-Disposition. */
    public function downloadName(): string
    {
        return sprintf(
            'timesheet-%s.pdf',
            $this->month_starts_on->format('Y-m'),
        );
    }
}
