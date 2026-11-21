<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courtroom_participant_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('simulation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_key', 60);
            $table->string('rubric_version', 100);
            $table->json('ai_recommendation');
            $table->json('criteria')->nullable();
            $table->json('activity')->nullable();
            $table->json('evidence')->nullable();
            $table->string('attendance', 24)->default('unmarked');
            $table->decimal('individual_score', 6, 2)->nullable();
            $table->decimal('activity_score', 4, 2)->nullable();
            $table->decimal('simulation_performance', 6, 2)->nullable();
            $table->decimal('teacher_final_grade', 6, 2)->nullable();
            $table->json('gradebook_columns');
            $table->text('comment')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('preliminary');
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('reassessment')->nullable();
            $table->timestamps();
            $table->unique(['simulation_session_id', 'student_id'], 'courtroom_one_grade_per_student');
            $table->index(['course_id', 'status', 'student_id'], 'courtroom_course_journal');
            $table->index(['student_id', 'status'], 'courtroom_student_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courtroom_participant_grades');
    }
};
