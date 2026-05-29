<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M13a — add employer_id to clients, backfill from owner's Self employer.
 *
 * Column stays nullable in the DB (sidesteps the doctrine/dbal requirement
 * for column modifications); the application layer (FormRequest +
 * Client::saving observer) enforces non-null.
 *
 * Cascade behaviour: restrictOnDelete because deleting an Employer with
 * Clients should require the user to move/delete those clients first
 * (matches the deletion guard in App\Models\Employer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('employer_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('employers')
                ->restrictOnDelete();
            $table->index('employer_id');
        });

        // Backfill: every existing client gets pointed at its owner's Self.
        DB::statement(<<<'SQL'
            UPDATE clients
            SET employer_id = (
                SELECT id
                FROM employers
                WHERE employers.owner_id = clients.owner_id
                  AND employers.is_self = 1
                LIMIT 1
            )
            WHERE employer_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['employer_id']);
            $table->dropIndex(['employer_id']);
            $table->dropColumn('employer_id');
        });
    }
};
