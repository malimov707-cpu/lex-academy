<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_resources', function (Blueprint $table): void {
            $table->string('local_path')->nullable();
            $table->string('local_format', 10)->nullable();
            $table->string('content_sha256', 64)->nullable();
            $table->unsignedBigInteger('content_bytes')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('import_source_url', 2000)->nullable();
            $table->string('license')->nullable();
            $table->string('license_url', 2000)->nullable();
            $table->text('attribution')->nullable();
            $table->json('contents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('library_resources', function (Blueprint $table): void {
            $table->dropColumn(['local_path', 'local_format', 'content_sha256', 'content_bytes', 'imported_at', 'import_source_url', 'license', 'license_url', 'attribution', 'contents']);
        });
    }
};
