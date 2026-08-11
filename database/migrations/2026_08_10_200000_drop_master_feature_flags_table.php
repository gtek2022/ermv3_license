<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop master_feature_flags — the feature was never wired up.
 *
 * There was a full CRUD screen, this table, and plumbing that carried the flags through config-sync
 * into encrypted storage on three client apps. Nothing read them: getFeatureFlags() exists in ipa-crm,
 * ipa-absensi and ermv3-new with no callers in any of them, and the table held zero rows in production
 * because nobody ever had a reason to fill it.
 *
 * The rollout_percentage column was worse than unused - it was validated and stored, but
 * buildFeatureFlags() only ever sent `enabled`. An operator setting 50% would have got a full rollout,
 * so the screen was promising something that could not happen.
 *
 * Safe to drop: zero rows, and verified before writing this. down() recreates the table exactly as
 * 2026_05_23_000006 built it, so this is reversible - though what it restores is an empty table, since
 * there was never anything in it to lose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('master_feature_flags');
    }

    public function down(): void
    {
        Schema::create('master_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 150)->index();
            $table->string('app_scope', 50)->default('*')->index();
            $table->boolean('enabled')->default(false)->index();
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['feature_key', 'app_scope']);
        });
    }
};
