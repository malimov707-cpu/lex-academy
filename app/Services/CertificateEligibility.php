<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CertificateEligibility
{
    public function result(Course $course, User $student): float
    {
        $enrolled = $course->enrollments()->where('student_id', $student->id)->whereIn('status', ['active', 'completed'])->exists();
        $lessons = Lesson::query()->whereIn('module_id', $course->modules()->select('id'))->where('status', 'published')->pluck('id');
        $completed = LessonProgress::query()->where('student_id', $student->id)->whereIn('lesson_id', $lessons)->where('status', 'completed')->count();
        if (! $enrolled || $course->status !== 'published' || $lessons->isEmpty() || $completed !== $lessons->count()) {
            throw ValidationException::withMessages(['certificate' => __('Barcha nashr qilingan darslar tugallangan va kursga biriktirilgan bo‘lishi kerak.')]);
        }
        foreach (Lesson::query()->whereIn('id', $lessons)->with('module')->get() as $lesson) {
            app(LessonCompletionPolicy::class)->ensureSatisfied($lesson, $student);
        }
        $scores = [];
        foreach ($course->assignments()->whereDoesntHave('legalCase', fn ($query) => $query->where('status', '!=', 'published'))->get() as $assignment) {
            $submission = $assignment->submissions()->where('student_id', $student->id)->where('status', '!=', 'in_progress')->orderByDesc('attempt')->orderByDesc('id')->first();
            $review = $submission?->reviews()->latest('id')->first();
            if (! $submission || $submission->status !== 'approved' || $submission->score === null || $review?->decision !== 'approved') {
                throw ValidationException::withMessages(['certificate' => __('Barcha topshiriqlar mentor tomonidan tasdiqlanishi kerak.')]);
            }
            $scores[] = min(100, $submission->score / $assignment->max_score * 100);
        }
        foreach ($course->quizzes()->get() as $quiz) {
            $passed = $quiz->attempts()->where('student_id', $student->id)->where('status', '!=', 'in_progress')
                ->where('score', '>=', $quiz->pass_score_percent)->whereDoesntHave('answers', fn ($query) => $query->whereNull('points_earned'))->orderByDesc('score')->first();
            if (! $passed) {
                throw ValidationException::withMessages(['certificate' => __('Barcha test va imtihonlar yakuniy baholanib, o‘tish balli olingan bo‘lishi kerak.')]);
            }
            $scores[] = (float) $passed->score;
        }

        return $scores === [] ? 100.0 : round(array_sum($scores) / count($scores), 2);
    }
}
