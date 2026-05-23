<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_app_features', function (Blueprint $table) {
            $table->id();
            $table->string('app_code', 50)->index();
            $table->string('feature_key', 100)->index();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['app_code', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_app_features');
    }
};
