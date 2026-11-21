<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legal_source_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_source_id')->constrained('legal_sources')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->char('content_sha256', 64)->nullable();
            $table->string('etag')->nullable();
            $table->string('source_last_modified')->nullable();
            $table->timestamp('checked_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['legal_source_id', 'checked_at']);
            $table->index(['status', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_source_checks');
    }
};
