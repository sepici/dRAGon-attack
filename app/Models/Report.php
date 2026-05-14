<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'week_starts_on',
        'generated_at',
        'file_path',
    ];

    protected $casts = [
        'week_starts_on' => 'date',
        'generated_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Absolute path on disk to the stored PDF. */
    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    /** Whether the PDF file still exists on disk. */
    public function fileExists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }

    /** Display-friendly filename for the Content-Disposition header on download. */
    public function downloadName(): string
    {
        return sprintf(
            'rag-report-%s.pdf',
            $this->week_starts_on->format('Y-m-d'),
        );
    }
}
