<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('education_groups')->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('kind')->default('lesson');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->string('location')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['class_schedule_id', 'student_id']);
        });
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('verification_code', 64)->unique();
            $table->string('student_name');
            $table->string('course_title');
            $table->string('issuer');
            $table->decimal('final_result', 5, 2);
            $table->timestamp('completed_at');
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'student_id']);
        });
        Schema::create('certificate_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->timestamp('verified_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_verifications');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('class_schedules');
    }
};
