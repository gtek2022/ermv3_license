<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `meta` (JSON) untuk simpan per-license overrides.
 *
 * Saat ini dipakai untuk:
 *   - meta.policy.heartbeat_tolerance — admin override per license
 *   - meta.policy.warning_days
 *
 * Format berbarengan dengan package License.meta supaya
 * LicensePolicyController bisa baca dari salah satu source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_companies', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('license_companies', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
