<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->date('deadline')->nullable();
            $table->foreignId('responsible_contact_id')
                ->nullable()
                ->constrained('contact_persons')
                ->nullOnDelete();
            $table->enum('status', ['R', 'A', 'G', 'B'])->default('R');
            $table->enum('moscow', ['M', 'S', 'C', 'W'])->nullable();
            $table->timestamps();

            $table->index('owner_id');
            $table->index('client_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
