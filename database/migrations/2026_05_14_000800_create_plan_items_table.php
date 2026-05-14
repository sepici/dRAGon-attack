<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per allocation in a plan period.
     *
     * Two flavours:
     *   • deliverable_id set → "I'm allocating N days to this deliverable
     *     this week / month / quarter."
     *   • deliverable_id null + ad_hoc_name set → "Unplanned work I had to
     *     do this week (e.g. emergency server intervention)." Filled in at
     *     review time, not when planning.
     */
    public function up(): void
    {
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_period_id')->constrained('plan_periods')->cascadeOnDelete();
            $table->foreignId('deliverable_id')->nullable()
                ->constrained('deliverables')->cascadeOnDelete();
            $table->string('ad_hoc_name', 200)->nullable();
            $table->text('ad_hoc_notes')->nullable();
            $table->decimal('allocated_days', 5, 1)->default(0);
            $table->decimal('days_spent', 5, 1)->default(0);
            $table->text('notes')->nullable();
            $table->enum('status', ['R', 'A', 'G', 'B'])->default('R');
            $table->timestamp('completed_at')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('plan_period_id');
            // Don't let the same deliverable be added twice to the same period.
            $table->unique(['plan_period_id', 'deliverable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};
