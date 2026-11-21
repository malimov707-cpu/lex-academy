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
        Schema::create('courtroom_evidence_annotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('simulation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scenario_evidence_id')->constrained('scenario_evidence')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->unsignedInteger('start_offset')->nullable();
            $table->unsignedInteger('end_offset')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['simulation_session_id', 'scenario_evidence_id', 'idempotency_key'], 'courtroom_annotation_request_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courtroom_evidence_annotations');
    }
};
