<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12a: milestones — an optional grouping layer between a project and its
 * deliverables. Use cases:
 *
 *   • Big projects (TCC has 28 deliverables) want phased structure.
 *   • Forward planning where you know "5 days on this chunk next month"
 *     before you know which specific deliverables it'll touch.
 *
 * Design notes:
 *   • A deliverable's milestone is OPTIONAL — small projects skip it.
 *   • A plan_item references EITHER a deliverable OR a milestone, never
 *     both. App-level guard in App\Models\PlanItem::saving; not enforced
 *     by a DB CHECK constraint (would need raw SQL, not portable).
 *   • Status is DERIVED on read from child deliverables + the
 *     scope_complete flag. No status column on the table — see
 *     App\Models\Milestone::derivedStatus().
 *   • target_hours is OPTIONAL — set it if you have a coarse-grained
 *     target before scoping deliverables; leave null to let the UI sum
 *     children.
 *
 * Cascade behaviour:
 *   • Delete a project → its milestones go away (cascade).
 *   • Delete a milestone → its child deliverables KEEP existing but
 *     their milestone_id is set null (nullOnDelete). Plan items
 *     targeting the milestone are deleted (cascadeOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Optional coarse-grained target. When null, the UI sums children.
            $table->decimal('target_hours', 6, 2)->nullable();

            $table->date('deadline')->nullable();
            $table->enum('moscow', ['M', 'S', 'C', 'W'])->nullable();

            // "Have I listed every deliverable that needs to be in this
            // milestone?" — gates the derived-Green status. Until this is
            // true, even all-Green children only push the milestone to Amber.
            $table->boolean('scope_complete')->default(false);

            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('project_id');
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->foreignId('milestone_id')
                ->nullable()
                ->after('project_id')
                ->constrained('milestones')
                ->nullOnDelete();
            $table->index('milestone_id');
        });

        Schema::table('plan_items', function (Blueprint $table) {
            $table->foreignId('milestone_id')
                ->nullable()
                ->after('deliverable_id')
                ->constrained('milestones')
                ->cascadeOnDelete();
            $table->index('milestone_id');
        });
    }

    public function down(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropForeign(['milestone_id']);
            $table->dropIndex(['milestone_id']);
            $table->dropColumn('milestone_id');
        });
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropForeign(['milestone_id']);
            $table->dropIndex(['milestone_id']);
            $table->dropColumn('milestone_id');
        });
        Schema::dropIfExists('milestones');
    }
};
