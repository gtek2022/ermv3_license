<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * log_audit — general admin action audit trail.
     * Covers all non-license admin actions: config changes, user management, etc.
     */
    public function up(): void
    {
        Schema::create('log_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('action', 100)->index(); // created|updated|deleted|restored|config_changed|flag_toggled
            $table->string('module', 80)->index(); // master_config|master_app|master_company|user_management
            $table->string('subject_type', 80)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('subject_label')->nullable(); // human-readable identifier
            $table->text('previous_state')->nullable();
            $table->text('new_state')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();

            $table->index(['module', 'action']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_audit');
    }
};
