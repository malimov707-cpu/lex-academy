<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_id')->constrained('simulation_scenarios')->restrictOnDelete();
            $table->foreignId('scenario_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('selected_role_key', 60);
            $table->string('status', 30)->default('created')->index();
            $table->string('current_stage_key', 80)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('elapsed_seconds')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index(['scenario_version_id', 'status']);
        });

        Schema::create('session_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('simulation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_key', 60);
            $table->string('actor_type', 20);
            $table->string('display_name');
            $table->string('status', 30)->default('ready');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['simulation_session_id', 'role_key']);
        });

        Schema::create('session_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('simulation_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('speaker_role_key', 60);
            $table->string('speaker_type', 20);
            $table->string('input_type', 20)->default('text');
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['simulation_session_id', 'sequence']);
        });

        Schema::create('session_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('simulation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scenario_evidence_id')->nullable()->constrained('scenario_evidence')->nullOnDelete();
            $table->string('actor_role_key', 60);
            $table->string('stage_key', 80);
            $table->string('action_type', 80);
            $table->longText('content')->nullable();
            $table->string('validation_status', 30);
            $table->text('validation_message')->nullable();
            $table->longText('ai_response')->nullable();
            $table->json('evaluation_metadata')->nullable();
            $table->uuid('idempotency_key');
            $table->timestamps();
            $table->unique(['simulation_session_id', 'idempotency_key']);
            $table->index(['simulation_session_id', 'stage_key']);
        });

        Schema::create('session_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('simulation_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('type', 80);
            $table->string('actor_role_key', 60)->nullable();
            $table->string('stage_key', 80)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['simulation_session_id', 'sequence']);
        });

        Schema::create('voice_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('simulation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 40)->default('browser');
            $table->string('status', 30)->default('created')->index();
            $table->boolean('transcript_consent')->default(false);
            $table->boolean('audio_recording')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['simulation_session_id', 'status']);
        });

        Schema::create('voice_transcript_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voice_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('speaker_role_key', 60);
            $table->longText('content');
            $table->boolean('is_final')->default(true);
            $table->unsignedInteger('started_at_ms')->nullable();
            $table->unsignedInteger('ended_at_ms')->nullable();
            $table->timestamps();
            $table->unique(['voice_session_id', 'sequence']);
        });

        Schema::create('evaluation_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('simulation_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('overall_score', 6, 2)->default(0);
            $table->string('generated_by', 30)->default('system');
            $table->json('strengths')->nullable();
            $table->json('improvements')->nullable();
            $table->json('next_exercises')->nullable();
            $table->timestamp('generated_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('evaluation_criterion_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 6, 2);
            $table->text('rationale');
            $table->json('evidence_action_ids')->nullable();
            $table->json('evidence_event_ids')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_result_id', 'evaluation_criterion_id']);
        });

        Schema::create('teacher_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 30);
            $table->decimal('adjusted_score', 6, 2)->nullable();
            $table->text('comment');
            $table->json('criterion_overrides')->nullable();
            $table->timestamps();
            $table->index(['reviewer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_reviews');
        Schema::dropIfExists('evaluation_criterion_results');
        Schema::dropIfExists('evaluation_results');
        Schema::dropIfExists('voice_transcript_segments');
        Schema::dropIfExists('voice_sessions');
        Schema::dropIfExists('session_events');
        Schema::dropIfExists('session_actions');
        Schema::dropIfExists('session_turns');
        Schema::dropIfExists('session_participants');
        Schema::dropIfExists('simulation_sessions');
    }
};
