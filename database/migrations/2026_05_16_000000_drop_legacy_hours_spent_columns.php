<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M8c: drop the legacy hours_spent counters now that time_logs is the
 * source of truth.
 *
 * Tables touched:
 *   • deliverables   — drop hours_spent
 *   • plan_items     — drop hours_spent, ad_hoc_name, ad_hoc_notes; first
 *                      delete the legacy ad-hoc rows (deliverable_id NULL)
 *
 * Before dropping deliverables.hours_spent we backfill whatever cumulative
 * value is on each row into a synthetic time_logs entry, so the derived
 * Deliverable::hours_spent accessor returns the same number after the
 * migration as before. Owner is taken from the parent project; date is
 * today (no per-day breakdown is recoverable from a single counter).
 *
 * No backfill from plan_items.hours_spent: those rows would each map to a
 * weekly date range, not a single date, and the per-deliverable cumulative
 * is already preserved via the deliverables backfill above.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfillDeliverableHoursSpentIntoTimeLogs();

        DB::table('plan_items')->whereNull('deliverable_id')->delete();

        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn('hours_spent');
        });

        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropColumn(['hours_spent', 'ad_hoc_name', 'ad_hoc_notes']);
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->decimal('hours_spent', 6, 2)->default(0)->after('target_hours');
        });

        Schema::table('plan_items', function (Blueprint $table) {
            $table->string('ad_hoc_name', 200)->nullable()->after('deliverable_id');
            $table->text('ad_hoc_notes')->nullable()->after('ad_hoc_name');
            $table->decimal('hours_spent', 6, 2)->default(0)->after('allocated_hours');
        });

        // Down does NOT restore the deleted ad-hoc plan_items rows or roll
        // back the backfill — those were destructive on the way up.
    }

    /**
     * One synthetic time_logs row per deliverable with hours_spent > 0,
     * dated to today, attributed to the project's owner.
     */
    private function backfillDeliverableHoursSpentIntoTimeLogs(): void
    {
        // Skip on a fresh test DB where time_logs hasn't been created yet
        // (paranoia — the M8a migration runs before this one, but better
        // safe than sorry on a corrupt migration order).
        if (! Schema::hasTable('time_logs') || ! Schema::hasColumn('deliverables', 'hours_spent')) {
            return;
        }

        $today = now()->toDateString();
        $now = now();

        DB::table('deliverables')
            ->where('hours_spent', '>', 0)
            ->orderBy('id')
            ->chunk(50, function ($deliverables) use ($today, $now) {
                foreach ($deliverables as $d) {
                    $ownerId = DB::table('projects')
                        ->where('id', $d->project_id)
                        ->value('owner_id');
                    if (! $ownerId) {
                        continue;
                    }

                    DB::table('time_logs')->insert([
                        'owner_id' => $ownerId,
                        'log_date' => $today,
                        'deliverable_id' => $d->id,
                        'ad_hoc_name' => null,
                        'hours' => $d->hours_spent,
                        'notes' => 'Backfilled from deliverables.hours_spent during M8c.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }
};
