<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('certificate_issuer');
            $table->string('support_email')->nullable();
            $table->string('support_phone', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_settings');
    }
};
