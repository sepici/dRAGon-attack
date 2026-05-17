<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M8f: monthly timesheet PDF history.
 *
 * One row per generated PDF. Mirrors the `reports` table — the file path
 * carries a YYYYMMDDHHMMSS suffix so re-generating doesn't overwrite the
 * previous file, and the DB tracks each generation as its own row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->date('month_starts_on'); // first day of the month
            $table->timestamp('generated_at');
            $table->string('file_path', 255); // relative to storage/app/
            $table->timestamps();

            $table->index(['owner_id', 'month_starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
