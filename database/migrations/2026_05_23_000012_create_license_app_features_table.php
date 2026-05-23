<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_app_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_app_id')->index();
            $table->string('app_code', 50)->index();
            $table->string('feature_key', 100)->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['license_app_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_app_features');
    }
};
