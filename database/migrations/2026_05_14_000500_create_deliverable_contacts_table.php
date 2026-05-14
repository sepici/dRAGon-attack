<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot for the many-to-many between deliverables and contact_persons.
     * A deliverable inherits one responsible contact from its project, and
     * may additionally have one or more contacts attached from the same
     * client (selected via dropdown on the deliverable form).
     */
    public function up(): void
    {
        Schema::create('deliverable_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('deliverables')->cascadeOnDelete();
            $table->foreignId('contact_person_id')->constrained('contact_persons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['deliverable_id', 'contact_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_contacts');
    }
};
