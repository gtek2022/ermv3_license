<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FLK terms in minutes, not just whole days.
 *
 * A term could only be expressed in days, so the shortest FLK anyone could issue lasted 24 hours.
 * That is far too coarse for a demo or a sales trial, where the useful unit is minutes.
 *
 * `license_duration_minutes` becomes the canonical term and is the only column the application
 * reads from here on. Existing rows are converted, so nothing already configured changes meaning:
 * a 30-day term becomes 43200 minutes and produces exactly the same deadline it did before.
 *
 * Why `license_duration_days` stays, given the model's own comment that two columns answering one
 * question is how they end up disagreeing.
 *
 * It is no longer a second answer - it is a derived mirror, written only by the application as
 * ceil(minutes / 1440) and never read. It exists for one reason: if this deploy is rolled back
 * while the database keeps its new rows, the older code reads `license_duration_days`, and a
 * minute-scale term created in the meantime would have a NULL there. Old code reads NULL as
 * perpetual - so a fifteen-minute trial would silently become a lifetime licence. That is the one
 * failure direction worth engineering against, because it gives away a paid module rather than
 * withholding one. Under rollback the same trial reads as one day instead: wrong, bounded, and
 * recoverable.
 *
 * Enforcement is unaffected and already minute-accurate. Deadlines live in
 * license_feature_activations.expires_at as a timestamp, and both the server and every client
 * compare that timestamp to the clock. This migration changes how a term is *expressed*, not how
 * it is *enforced*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_app_features', function (Blueprint $table) {
            // NULL = perpetual, matching license_duration_days. Not zero: a term of no time at all
            // is a different thing, and not one anybody would mean to configure.
            $table->unsignedInteger('license_duration_minutes')
                ->nullable()
                ->after('license_duration_days');
        });

        // Carry every existing term across unchanged. Done here rather than left to the application
        // because until this runs, every previously configured term would read as perpetual - the
        // same giveaway the mirror above exists to prevent, only certain instead of hypothetical.
        DB::table('master_app_features')
            ->whereNotNull('license_duration_days')
            ->where('license_duration_days', '>', 0)
            ->update([
                'license_duration_minutes' => DB::raw('license_duration_days * 1440'),
            ]);
    }

    public function down(): void
    {
        // license_duration_days was kept in sync throughout, so dropping this column loses only the
        // sub-day precision - day-scale terms survive the reversal intact.
        Schema::table('master_app_features', function (Blueprint $table) {
            $table->dropColumn('license_duration_minutes');
        });
    }
};
