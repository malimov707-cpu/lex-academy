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
        Schema::create('gradebook_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('simulation_session_id')->constrained()->cascadeOnDelete();
            $table->string('column_key', 40);
            $table->decimal('score', 6, 2);
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['course_id', 'student_id', 'simulation_session_id', 'column_key'], 'courtroom_gradebook_unique_entry');
            $table->index(['course_id', 'student_id', 'approved_at'], 'courtroom_gradebook_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gradebook_entries');
    }
};
