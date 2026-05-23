<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_app_configs', function (Blueprint $table) {
            $table->id();
            $table->string('app_code', 50)->index();
            $table->string('config_key', 150)->index();
            $table->text('config_value')->nullable();
            $table->string('config_type', 20)->default('string');
            $table->string('config_scope', 30)->default('global')->index();
            $table->string('environment', 30)->nullable()->index();
            $table->string('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['app_code', 'config_key', 'config_scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_app_configs');
    }
};
