<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M13a — add employer_id to time_logs.
 *
 * Derivation rules (mirrored by the application layer on every save):
 *
 *   • Deliverable-linked log → take the employer from the deliverable's
 *     chain: deliverable → project → client → employer.
 *
 *   • Ad-hoc log (no deliverable) → the user picks the employer at
 *     create-time. Defaults to the user's Self employer when omitted.
 *
 * Column stays nullable in the schema for the same reason as clients
 * (no doctrine/dbal); model-layer guards enforce it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_logs', function (Blueprint $table) {
            $table->foreignId('employer_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('employers')
                ->restrictOnDelete();
            $table->index('employer_id');
        });

        // Backfill 1 — deliverable-linked logs derive from the chain.
        DB::statement(<<<'SQL'
            UPDATE time_logs
            SET employer_id = (
                SELECT clients.employer_id
                FROM deliverables
                INNER JOIN projects ON projects.id = deliverables.project_id
                INNER JOIN clients ON clients.id = projects.client_id
                WHERE deliverables.id = time_logs.deliverable_id
                LIMIT 1
            )
            WHERE time_logs.deliverable_id IS NOT NULL
              AND time_logs.employer_id IS NULL
        SQL);

        // Backfill 2 — ad-hoc logs go to the owner's Self employer.
        DB::statement(<<<'SQL'
            UPDATE time_logs
            SET employer_id = (
                SELECT id
                FROM employers
                WHERE employers.owner_id = time_logs.owner_id
                  AND employers.is_self = 1
                LIMIT 1
            )
            WHERE time_logs.deliverable_id IS NULL
              AND time_logs.employer_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('time_logs', function (Blueprint $table) {
            $table->dropForeign(['employer_id']);
            $table->dropIndex(['employer_id']);
            $table->dropColumn('employer_id');
        });
    }
};
