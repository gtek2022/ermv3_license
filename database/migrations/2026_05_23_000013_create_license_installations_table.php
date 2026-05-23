<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_installations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_app_id')->index();
            $table->unsignedBigInteger('license_company_id')->index();
            $table->string('app_code', 50)->index();
            $table->string('installation_uuid', 64)->unique()->index();
            $table->string('fingerprint', 64)->index();
            $table->string('hostname')->nullable();
            $table->string('domain')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('violation_counter')->default(0);
            $table->timestamp('first_verified_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['license_company_id', 'status']);
            $table->index(['app_code', 'status']);
            $table->index(['fingerprint', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_installations');
    }
};
