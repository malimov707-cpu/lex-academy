<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tutor_sessions', function (Blueprint $table): void {
            $table->dropUnique(['student_id', 'lesson_id']);
            $table->foreignId('lesson_id')->nullable()->change();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('title')->nullable();
            $table->json('metadata')->nullable();
        });

        Schema::table('ai_tutor_messages', function (Blueprint $table): void {
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_tutor_messages', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });

        Schema::table('ai_tutor_sessions', function (Blueprint $table): void {
            $table->dropForeign(['course_id']);
            $table->dropColumn(['course_id', 'title', 'metadata']);
            $table->foreignId('lesson_id')->nullable(false)->change();
            $table->unique(['student_id', 'lesson_id']);
        });
    }
};
