<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add role + capacity columns to the users table.
     *
     * role: one of admin | user | viewer. Strict separation — each role has
     *       a different post-login experience.
     * weekly_capacity_days / monthly_capacity_days: per-user planning ceiling,
     *       used by Weekly Plan and Monthly Plan to flag scope-vs-capacity.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'user', 'viewer'])
                ->default('user')
                ->after('password');

            $table->decimal('weekly_capacity_days', 3, 1)
                ->default(5.0)
                ->after('role');

            $table->decimal('monthly_capacity_days', 4, 1)
                ->default(20.0)
                ->after('weekly_capacity_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'weekly_capacity_days', 'monthly_capacity_days']);
        });
    }
};
