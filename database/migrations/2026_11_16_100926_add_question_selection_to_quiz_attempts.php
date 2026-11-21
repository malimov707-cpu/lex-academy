<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->boolean('shuffle_options')->default(false);
            $table->unsignedInteger('random_question_count')->nullable();
        });
        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->json('question_order')->nullable();
            $table->json('option_order')->nullable();
        });
        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->constrained('education_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropColumn(['question_order', 'option_order']));
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn(['shuffle_options', 'random_question_count']));
    }
};
