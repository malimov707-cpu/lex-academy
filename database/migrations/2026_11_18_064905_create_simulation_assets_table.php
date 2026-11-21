<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('kind', 30);
            $table->string('path');
            $table->string('source_url', 2048);
            $table->string('license', 80);
            $table->string('license_path');
            $table->string('checksum', 64);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('triangle_count')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('enabled')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_assets');
    }
};
