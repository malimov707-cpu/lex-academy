<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_ai_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind');
            $table->string('topic');
            $table->text('sources');
            $table->longText('content');
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'teacher_id']);
        });
        Schema::create('mentor_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mentor_assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('mentor_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('course_discussions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->index(['course_id', 'id']);
        });
        Schema::create('discussion_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discussion_id')->constrained('course_discussions')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['discussion_id', 'id']);
        });
        Schema::create('lesson_block_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('answer');
            $table->boolean('is_correct');
            $table->timestamps();
            $table->unique(['lesson_block_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_block_responses');
        Schema::dropIfExists('discussion_replies');
        Schema::dropIfExists('course_discussions');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('mentor_conversations');
        Schema::dropIfExists('teacher_ai_drafts');
    }
};
