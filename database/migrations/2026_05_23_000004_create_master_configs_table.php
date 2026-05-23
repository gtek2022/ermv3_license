<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key', 150)->unique()->index();
            $table->text('config_value')->nullable();
            $table->string('config_type', 20)->default('string');
            $table->string('category', 80)->index();
            $table->string('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_configs');
    }
};
