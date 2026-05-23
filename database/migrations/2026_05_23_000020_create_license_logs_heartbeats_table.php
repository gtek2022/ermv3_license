<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_logs_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installation_id')->index();
            $table->unsignedBigInteger('license_company_id')->index();
            $table->string('app_code', 50)->index();
            $table->string('installation_uuid', 64)->index();
            $table->string('fingerprint', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('domain')->nullable();
            $table->string('status', 20)->index(); // verified|failed|rejected|suspicious
            $table->string('failure_reason')->nullable();
            $table->string('config_version', 30)->nullable();
            $table->unsignedInteger('violation_counter')->default(0);
            $table->json('response_policy')->nullable(); // policy sent back to client
            $table->timestamp('heartbeat_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_logs_heartbeats');
    }
};
