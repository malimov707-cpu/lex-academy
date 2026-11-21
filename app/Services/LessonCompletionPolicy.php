<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LessonCompletionPolicy
{
    public function ensureSatisfied(Lesson $lesson, User $student): void
    {
        $rules = $lesson->completion_rules ?? [];
        if ($rules['require_interactive'] ?? false) {
            $ids = $lesson->blocks()->where('type', 'interactive_question')->pluck('id');
            $correct = DB::table('lesson_block_responses')->where('student_id', $student->id)->whereIn('lesson_block_id', $ids)->where('is_correct', true)->count();
            if ($ids->isEmpty() || $correct !== $ids->count()) {
                throw ValidationException::withMessages(['completion' => __('Interaktiv savollarga to‘g‘ri javob bering.')]);
            }
        }
        if (! empty($rules['quiz_id'])) {
            $quiz = Quiz::query()->whereKey($rules['quiz_id'])->where('course_id', $lesson->module->course_id)->first();
            if (! $quiz || ! $quiz->attempts()->where('student_id', $student->id)->where('status', '!=', 'in_progress')
                ->where('score', '>=', $quiz->pass_score_percent)->whereDoesntHave('answers', fn ($query) => $query->whereNull('points_earned'))->exists()) {
                throw ValidationException::withMessages(['completion' => __('Dars uchun belgilangan testdan o‘tish kerak.')]);
            }
        }
        if (! empty($rules['assignment_id'])) {
            $assignment = Assignment::query()->whereKey($rules['assignment_id'])->where('course_id', $lesson->module->course_id)->first();
            $submission = $assignment?->submissions()->where('student_id', $student->id)->where('status', '!=', 'in_progress')->orderByDesc('attempt')->orderByDesc('id')->first();
            if (! $submission || $submission->status !== 'approved' || $submission->reviews()->latest('id')->first()?->decision !== 'approved') {
                throw ValidationException::withMessages(['completion' => __('Belgilangan topshiriq mentor tomonidan tasdiqlanishi kerak.')]);
            }
        }
    }
}
