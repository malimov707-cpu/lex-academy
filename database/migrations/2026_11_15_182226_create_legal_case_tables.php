<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->text('situation');
            $table->text('facts');
            $table->text('legal_issue');
            $table->text('legislation');
            $table->text('analysis_questions');
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });
        Schema::create('legal_case_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('analysis');
            $table->text('feedback');
            $table->float('confidence')->nullable();
            $table->timestamps();
            $table->index(['legal_case_id', 'student_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_case_feedback');
        Schema::dropIfExists('legal_cases');
    }
};
