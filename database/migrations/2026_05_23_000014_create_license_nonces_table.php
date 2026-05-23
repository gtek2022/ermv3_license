<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_nonces', function (Blueprint $table) {
            $table->id();
            $table->string('nonce', 64)->unique()->index();
            $table->string('installation_uuid', 64)->nullable()->index();
            $table->timestamp('used_at')->useCurrent();
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_nonces');
    }
};
