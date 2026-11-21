<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Services\LessonCompletionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LearningController extends Controller
{
    public function show(Request $request, Course $course): View
    {
        $user = $request->user();

        $enrolled = CourseEnrollment::query()
            ->where('student_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($enrolled && $course->status === 'published', 403);

        $lessons = Lesson::query()->inLearningOrder()
            ->where('lessons.status', 'published')
            ->whereIn('module_id', $course->modules()->select('id'))
            ->get();

        $progress = LessonProgress::query()
            ->where('student_id', $user->id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()->keyBy('lesson_id');

        return view('student.course', [
            'course' => $course->load('category', 'teacher'),
            'modules' => $course->modules()->with('lessons')->orderBy('sort_order')->get(),
            'quizzes' => $course->quizzes()->get(),
            'lessons' => $lessons,
            'progress' => $progress,
        ]);
    }

    public function showLesson(Request $request, Course $course, Lesson $lesson): View
    {
        $user = $request->user();

        $belongs = $course->modules()->where('id', $lesson->module_id)->exists();
        abort_unless($belongs, 404);

        $enrolled = CourseEnrollment::query()
            ->where('student_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();
        abort_unless($enrolled, 403);

        abort_unless($lesson->status === 'published', 404);

        $progress = LessonProgress::query()
            ->where('student_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        $lessons = Lesson::query()->inLearningOrder()
            ->where('lessons.status', 'published')
            ->whereIn('module_id', $course->modules()->select('id'))
            ->get();

        $currentIndex = $lessons->search(fn (Lesson $l): bool => $l->id === $lesson->id);

        return view('student.lesson', [
            'course' => $course,
            'responses' => DB::table('lesson_block_responses')->where('student_id', $user->id)->whereIn('lesson_block_id', $lesson->blocks()->select('id'))->get()->keyBy('lesson_block_id'),
            'lesson' => $lesson->load('materials', 'module', 'blocks'),
            'progress' => $progress,
            'lessons' => $lessons,
            'previousLesson' => $currentIndex > 0 ? $lessons[$currentIndex - 1] : null,
            'nextLesson' => $currentIndex < $lessons->count() - 1 ? $lessons[$currentIndex + 1] : null,
        ]);
    }

    public function completeLesson(Request $request, Course $course, Lesson $lesson, LessonCompletionPolicy $policy): RedirectResponse
    {
        $user = $request->user();

        $policy->ensureSatisfied($lesson, $user);
        $criteria = $lesson->completion_criteria ?? [];
        abort_if($criteria !== [] && ! $request->boolean('confirm_criteria'), 400, __('Completion criteria not confirmed'));

        LessonProgress::query()->updateOrCreate(
            ['lesson_id' => $lesson->id, 'student_id' => $user->id],
            ['status' => 'completed', 'completion_percent' => 100, 'completed_at' => now()],
        );

        return back()->with('status', __('Dars tugallandi.'));
    }
}
