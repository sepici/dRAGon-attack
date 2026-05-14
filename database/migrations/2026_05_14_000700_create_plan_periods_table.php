<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plan period is one row per (owner, kind, starts_on). They're auto-
     * created on first visit and re-used by any subsequent activity in that
     * same window.
     */
    public function up(): void
    {
        Schema::create('plan_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->enum('kind', ['weekly', 'monthly', 'quarterly']);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['owner_id', 'kind', 'starts_on']);
            $table->index(['owner_id', 'kind', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_periods');
    }
};
