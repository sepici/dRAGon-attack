<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M13c — employer_viewers — many-to-many join granting a viewer User
 * read access to a specific Employer.
 *
 * A row here says: "User V (role=viewer) can see Employer E's tracker data."
 *
 * Backfill: existing viewer accounts (created under M1's "viewer = global
 * read-only") are auto-granted access to every employer that exists at
 * upgrade time. This preserves their current scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('employers')->cascadeOnDelete();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            // A viewer can only be granted access to a given employer once.
            $table->unique(['employer_id', 'viewer_id']);
            $table->index('viewer_id');
        });

        // Backfill: every existing viewer gets a grant on every existing
        // employer. No-op on a fresh install.
        $viewerRole = UserRole::Viewer->value;
        $now = now();
        $rows = DB::table('users')
            ->where('role', $viewerRole)
            ->pluck('id')
            ->flatMap(function ($viewerId) use ($now) {
                return DB::table('employers')
                    ->pluck('id')
                    ->map(fn ($employerId) => [
                        'employer_id' => $employerId,
                        'viewer_id' => $viewerId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
            })
            ->all();

        if (! empty($rows)) {
            DB::table('employer_viewers')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_viewers');
    }
};
