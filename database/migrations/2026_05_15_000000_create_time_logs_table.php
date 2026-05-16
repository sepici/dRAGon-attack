<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily time log — the source of truth for "how many hours did I spend on
 * what, when?". Each row represents a chunk of work on one day. Two flavours:
 *
 *   • deliverable_id set → time logged against a tracked deliverable.
 *   • deliverable_id null + ad_hoc_name set → unplanned work (server
 *     intervention, an urgent client call, etc.).
 *
 * deliverables.hours_spent and plan_items.hours_spent will be DERIVED from
 * this table (sums by deliverable_id and by date range) once M8c lands.
 * For now the columns coexist so existing review flow keeps working.
 *
 * Indexes:
 *   • (owner_id, log_date)   — primary lookup pattern (load a user's day).
 *   • (deliverable_id)        — for the "how many hours on X" aggregate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->date('log_date');
            $table->foreignId('deliverable_id')
                ->nullable()
                ->constrained('deliverables')
                ->cascadeOnDelete();
            $table->string('ad_hoc_name', 200)->nullable();
            $table->decimal('hours', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'log_date']);
            $table->index('deliverable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
