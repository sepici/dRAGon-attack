<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per generated PDF. Multiple generations for the same week
     * each get their own row (history preserved) — the file path includes
     * a timestamp so they don't overwrite each other on disk.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_starts_on');
            $table->timestamp('generated_at');
            $table->string('file_path', 255); // relative to storage/app/
            $table->timestamps();

            $table->index(['owner_id', 'week_starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
