<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_app_features', function (Blueprint $table) {
            // Whether this feature requires a separate license key to activate
            $table->boolean('requires_license')->default(false)->after('is_active')->index();

            // The license key for this specific feature (generated when requires_license = true)
            // Stored as hash — plain key shown once at creation
            $table->string('feature_license_key_hash', 64)->nullable()->after('requires_license');

            // Encrypted original key stored in meta for retrieval
            $table->text('feature_license_key_encrypted')->nullable()->after('feature_license_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('master_app_features', function (Blueprint $table) {
            $table->dropColumn([
                'requires_license',
                'feature_license_key_hash',
                'feature_license_key_encrypted',
            ]);
        });
    }
};
