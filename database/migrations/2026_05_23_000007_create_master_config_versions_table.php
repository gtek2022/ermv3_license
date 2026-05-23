<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_config_versions', function (Blueprint $table) {
            $table->id();
            $table->string('config_type', 50)->index();
            $table->unsignedBigInteger('config_id')->index();
            $table->string('config_key', 150);
            $table->text('previous_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['config_type', 'config_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_config_versions');
    }
};
