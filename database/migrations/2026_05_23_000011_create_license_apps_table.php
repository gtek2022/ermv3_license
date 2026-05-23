<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_company_id')->index();
            $table->string('app_code', 50)->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable()->index();
            $table->unsignedInteger('max_installations')->default(1);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['license_company_id', 'app_code']);
            $table->index(['license_company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_apps');
    }
};
