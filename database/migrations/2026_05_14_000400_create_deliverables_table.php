<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('target_days', 5, 1)->default(0);
            $table->decimal('days_spent', 5, 1)->default(0);
            $table->date('deadline')->nullable();
            $table->enum('status', ['R', 'A', 'G', 'B'])->default('R');
            $table->enum('moscow', ['M', 'S', 'C', 'W'])->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverables');
    }
};
