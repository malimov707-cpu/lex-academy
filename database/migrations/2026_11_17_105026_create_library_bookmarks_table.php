<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_resource_id')->constrained()->cascadeOnDelete();
            $table->string('state', 20)->default('saved');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'library_resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_bookmarks');
    }
};
