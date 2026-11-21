<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;

class LearningAnalytics
{
    /** @return array<string, mixed> */
    public function forCourse(Course $course, User $student): array
    {
        $lessons = Lesson::query()->inLearningOrder()->where('course_modules.course_id', $course->id)->where('lessons.status', 'published')->get();
        $progress = LessonProgress::query()->where('student_id', $student->id)->whereIn('lesson_id', $lessons->pluck('id'))->get()->keyBy('lesson_id');
        $completed = $progress->where('status', 'completed')->count();
        $next = $lessons->first(fn (Lesson $lesson): bool => $progress->get($lesson->id)?->status !== 'completed');
        $attempts = QuizAttempt::query()->where('student_id', $student->id)->whereHas('quiz', fn ($query) => $query->where('course_id', $course->id))
            ->where('status', '!=', 'in_progress')->with(['quiz', 'answers' => fn ($query) => $query->whereHas('question', fn ($question) => $question->where('course_id', $course->id))->with('question')])->latest('id')->get();
        $submissions = AssignmentSubmission::query()->where('student_id', $student->id)->whereHas('assignment', fn ($query) => $query->where('course_id', $course->id))
            ->with('assignment', 'reviews.mentor')->latest('id')->get();
        $answers = $attempts->flatMap->answers->sortByDesc('id')->unique('question_id');
        $weak = $answers->filter(fn (QuizAnswer $answer): bool => $answer->is_correct !== null && ! (bool) $answer->is_correct)->values();
        $strong = $answers->filter(fn (QuizAnswer $answer): bool => (bool) $answer->is_correct)->values();
        $timed = $submissions->filter(fn (AssignmentSubmission $submission): bool => $submission->status !== 'in_progress' && $submission->submitted_at !== null && $submission->assignment->deadline !== null);
        $onTime = $timed->filter(fn (AssignmentSubmission $submission): bool => $submission->submitted_at->lte($submission->assignment->deadline))->count();
        $final = $attempts->filter(fn (QuizAttempt $attempt): bool => ! $attempt->answers->contains(fn ($answer) => $answer->points_earned === null));

        return ['lessons' => $lessons, 'progress' => $progress, 'completed' => $completed, 'total' => $lessons->count(),
            'percent' => $lessons->isEmpty() ? 0.0 : round($completed / $lessons->count() * 100, 2), 'next_lesson' => $next,
            'attempts' => $attempts, 'submissions' => $submissions, 'weak' => $weak, 'strong' => $strong,
            'quiz_average' => $final->isEmpty() ? null : round((float) $final->avg('score'), 2),
            'on_time_percent' => $timed->isEmpty() ? null : round($onTime / $timed->count() * 100, 2)];
    }
}
