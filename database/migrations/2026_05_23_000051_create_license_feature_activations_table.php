<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * license_feature_activations
     *
     * Tracks which installations have activated which feature licenses.
     * One record per (installation + feature_key) pair.
     */
    public function up(): void
    {
        Schema::create('license_feature_activations', function (Blueprint $table) {
            $table->id();
            $table->string('app_code', 50)->index();
            $table->string('feature_key', 100)->index();
            $table->string('feature_license_key_hash', 64)->index();
            $table->string('installation_uuid', 64)->index();
            $table->string('fingerprint', 64)->nullable();
            $table->string('status', 20)->default('active')->index(); // active|revoked|suspended
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['feature_key', 'installation_uuid']);
            $table->index(['app_code', 'feature_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_feature_activations');
    }
};
