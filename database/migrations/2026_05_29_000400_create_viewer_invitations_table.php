<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M13c — viewer_invitations — token-gated invites to read-only access.
 *
 * Flow:
 *   1. Inviter (a regular User) creates a row here with an email + the
 *      employer_ids they want to grant. A random `token` is generated.
 *   2. Inviter shares the magic link `/viewer-invitations/{token}` with
 *      the recipient (via email if SMTP is configured; otherwise the
 *      link is shown inline so the inviter can copy it).
 *   3. Recipient opens the link, sets a password. The system either
 *      creates a new viewer User account or reuses an existing one
 *      matching the email; then writes employer_viewers rows for each
 *      granted employer.
 *   4. `accepted_at` is set; subsequent visits to the link 404 (token
 *      is single-use).
 *
 * `employer_ids` is stored as a JSON array. Once the invite is accepted,
 * the canonical grant lives in employer_viewers; the JSON is just the
 * "pending grant set" for the period before acceptance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viewer_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('email', 180)->index();
            $table->string('name', 200)->nullable();
            $table->string('token', 80)->unique();
            $table->json('employer_ids');
            $table->text('message')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('viewer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Set to the viewer\'s user id on acceptance.');
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewer_invitations');
    }
};
