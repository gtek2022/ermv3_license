<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('master_feature_flags');
    }
};
