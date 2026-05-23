<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_logs_suspicious', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installation_id')->nullable()->index();
            $table->unsignedBigInteger('license_company_id')->nullable()->index();
            $table->string('app_code', 50)->nullable()->index();
            $table->string('installation_uuid', 64)->nullable()->index();
            $table->string('event_type', 80)->index(); // fingerprint_mismatch|blacklisted|revoked_attempt|replay_attack|invalid_nonce
            $table->string('registered_fingerprint', 64)->nullable();
            $table->string('received_fingerprint', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('domain')->nullable();
            $table->text('details')->nullable();
            $table->string('severity', 20)->default('warning')->index(); // info|warning|critical
            $table->boolean('is_reviewed')->default(false)->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_logs_suspicious');
    }
};
