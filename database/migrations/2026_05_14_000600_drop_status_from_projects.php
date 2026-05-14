<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Project status is now derived from its deliverables' statuses (see
     * Project::getStatusAttribute and Status::rollup). Drop the manually-set
     * column so it can't drift from the computed value.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('status', ['R', 'A', 'G', 'B'])->default('R')->after('responsible_contact_id');
            $table->index('status');
        });
    }
};
