<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * license_logs_audit — append-only audit trail for all license actions.
     * Events: activated, suspended, cancelled, renewed, revoked, blacklisted, policy_updated, etc.
     */
    public function up(): void
    {
        Schema::create('license_logs_audit', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80)->index(); // activated|suspended|cancelled|renewed|revoked|blacklisted|policy_updated
            $table->string('subject_type', 50)->index(); // license_company|license_app|license_installation
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('subject_hashid', 20)->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index(); // admin user id
            $table->string('actor_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('previous_state')->nullable(); // JSON snapshot
            $table->text('new_state')->nullable();      // JSON snapshot
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_logs_audit');
    }
};
