<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M13a — employers — an entity above Client in the ownership chain.
 *
 * A user can have multiple employers (e.g. a freelancer working for two
 * agencies) plus an auto-created "Self" employer (is_self=true) that
 * represents their own one-person work. Self is always present, never
 * deletable, name fixed at "Self".
 *
 * Ownership chain becomes:
 *   User → Employer → Client → Project → Milestone/Deliverable → TimeLog
 *
 * Backfill: every existing user gets a Self employer in this migration.
 * The follow-up migrations point clients + time_logs at the right employer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 200);

            // Marks the auto-created Self row. Application layer guarantees
            // exactly one Self per user (no portable partial-unique index that
            // works on both MySQL and SQLite).
            $table->boolean('is_self')->default(false);

            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('owner_id');
            $table->index(['owner_id', 'is_self']);
        });

        // Backfill: one Self employer per existing user. Using bulk insert to
        // keep large user counts cheap on the upgrade.
        $now = now();
        $rows = DB::table('users')
            ->orderBy('id')
            ->get(['id'])
            ->map(fn ($u) => [
                'owner_id' => $u->id,
                'name' => 'Self',
                'is_self' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if (! empty($rows)) {
            DB::table('employers')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employers');
    }
};
