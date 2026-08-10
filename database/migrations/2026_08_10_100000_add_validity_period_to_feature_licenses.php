<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A validity period for feature licence keys (FLK).
 *
 * Until now an FLK was permanent: once an installation redeemed it the module stayed unlocked
 * forever, and the only way back was an administrator revoking it by hand. That leaves no way to
 * sell a module for a term, and no way to hand one out on trial.
 *
 * Two columns, because the duration and the deadline are different facts:
 *
 *   master_app_features.license_duration_days
 *     How long this FLK grants access *for*, counted from the moment an installation redeems it.
 *     A property of the key, decided when the feature is created. NULL means perpetual, which is
 *     what every existing FLK is, so nothing already issued changes behaviour.
 *
 *   license_feature_activations.expires_at
 *     When this particular installation's access actually ends. Stamped at activation as
 *     now() + license_duration_days, and NULL when the key is perpetual.
 *
 * The deadline is stored per activation rather than recomputed from activated_at on every read.
 * That is deliberate: it survives the duration being edited afterwards (an installation keeps the
 * term it was actually sold), and it lets a term be extended for one customer without touching the
 * feature everybody else redeems.
 *
 * This mirrors license_app_features.valid_until, which is the same idea one layer up - the
 * entitlement a licence carries - and is read the same way in ConfigSyncController:
 * active when status is active AND (no deadline OR the deadline is still ahead).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_app_features', function (Blueprint $table) {
            // NULL = perpetual. Not zero: zero would be a term of no days at all, which is a
            // different thing and one nobody would mean to configure.
            $table->unsignedInteger('license_duration_days')
                ->nullable()
                ->after('requires_license');
        });

        Schema::table('license_feature_activations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('activated_at');

            // The sweep command and the catalogue builder both ask "which of this installation's
            // activations are still live", which reads status and expires_at together.
            $table->index(['installation_uuid', 'status', 'expires_at'], 'lfa_live_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('license_feature_activations', function (Blueprint $table) {
            $table->dropIndex('lfa_live_lookup_idx');
            $table->dropColumn('expires_at');
        });

        Schema::table('master_app_features', function (Blueprint $table) {
            $table->dropColumn('license_duration_days');
        });
    }
};
