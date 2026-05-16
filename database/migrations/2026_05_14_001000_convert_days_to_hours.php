<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Switch the storage unit for all time fields from days (decimal 5,1)
     * to hours (decimal 6,2). 1 day = 8 hours. Existing data is multiplied
     * by 8 in-place; old columns are then dropped.
     */
    public function up(): void
    {
        // -------- Deliverables --------
        Schema::table('deliverables', function (Blueprint $table) {
            $table->decimal('target_hours', 6, 2)->default(0)->after('description');
            $table->decimal('hours_spent', 6, 2)->default(0)->after('target_hours');
        });
        DB::statement('UPDATE deliverables SET target_hours = target_days * 8, hours_spent = days_spent * 8');
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn(['target_days', 'days_spent']);
        });

        // -------- Plan items --------
        Schema::table('plan_items', function (Blueprint $table) {
            $table->decimal('allocated_hours', 6, 2)->default(0)->after('ad_hoc_notes');
            $table->decimal('hours_spent', 6, 2)->default(0)->after('allocated_hours');
        });
        DB::statement('UPDATE plan_items SET allocated_hours = allocated_days * 8, hours_spent = days_spent * 8');
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropColumn(['allocated_days', 'days_spent']);
        });

        // -------- Users (capacity) --------
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('weekly_capacity_hours', 5, 2)->default(40.00)->after('monthly_capacity_days');
            $table->decimal('monthly_capacity_hours', 6, 2)->default(160.00)->after('weekly_capacity_hours');
        });
        DB::statement('UPDATE users SET weekly_capacity_hours = weekly_capacity_days * 8, monthly_capacity_hours = monthly_capacity_days * 8');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weekly_capacity_days', 'monthly_capacity_days']);
        });
    }

    public function down(): void
    {
        // Inverse: rebuild days columns from hours / 8 and drop the hours columns.

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('weekly_capacity_days', 3, 1)->default(5.0)->after('monthly_capacity_hours');
            $table->decimal('monthly_capacity_days', 4, 1)->default(20.0)->after('weekly_capacity_days');
        });
        DB::statement('UPDATE users SET weekly_capacity_days = weekly_capacity_hours / 8, monthly_capacity_days = monthly_capacity_hours / 8');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weekly_capacity_hours', 'monthly_capacity_hours']);
        });

        Schema::table('plan_items', function (Blueprint $table) {
            $table->decimal('allocated_days', 5, 1)->default(0)->after('ad_hoc_notes');
            $table->decimal('days_spent', 5, 1)->default(0)->after('allocated_days');
        });
        DB::statement('UPDATE plan_items SET allocated_days = allocated_hours / 8, days_spent = hours_spent / 8');
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropColumn(['allocated_hours', 'hours_spent']);
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->decimal('target_days', 5, 1)->default(0)->after('description');
            $table->decimal('days_spent', 5, 1)->default(0)->after('target_days');
        });
        DB::statement('UPDATE deliverables SET target_days = target_hours / 8, days_spent = hours_spent / 8');
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn(['target_hours', 'hours_spent']);
        });
    }
};
