<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('catalog_key', 100)->unique();
            $table->string('title');
            $table->string('type', 30)->index();
            $table->string('topic', 100)->index();
            $table->text('description');
            $table->longText('body')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('publisher');
            $table->string('language', 20)->default('uz-latin');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('access', 20)->default('open');
            $table->string('status', 20)->default('draft')->index();
            $table->date('checked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_resources');
    }
};
