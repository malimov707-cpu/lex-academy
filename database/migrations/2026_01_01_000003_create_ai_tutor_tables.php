<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tutor_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 30)->default('explain');
            $table->timestamps();
            $table->unique(['student_id', 'lesson_id']);
        });

        Schema::create('ai_tutor_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_tutor_sessions')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->float('confidence')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tutor_messages');
        Schema::dropIfExists('ai_tutor_sessions');
    }
};
