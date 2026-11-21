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
        Schema::create('legal_document_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_type'); // claim, application, complaint, contract, demand
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->json('analysis')->nullable();
            $table->string('status')->default('draft'); // draft, analyzed, submitted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_drafts');
    }
};
