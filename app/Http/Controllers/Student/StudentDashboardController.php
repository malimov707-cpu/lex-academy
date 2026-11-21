<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ClassSchedule;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\MentorAssignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $enrollments = CourseEnrollment::query()
            ->where('student_id', $user->id)
            ->where('status', 'active')->whereHas('course', fn ($query) => $query->where('status', 'published'))
            ->with('course.category', 'course.teacher')
            ->get();

        $courseIds = $enrollments->pluck('course_id');

        $publishedLessons = Lesson::query()
            ->inLearningOrder()
            ->where('lessons.status', 'published')
            ->whereIn('course_modules.course_id', $courseIds)
            ->with('module:id,course_id')
            ->select('lessons.id', 'lessons.module_id', 'lessons.title')->get();
        $publishedLessonIds = $publishedLessons->pluck('id');

        $completedProgress = LessonProgress::query()
            ->where('student_id', $user->id)
            ->where('status', 'completed')
            ->whereIn('lesson_id', $publishedLessonIds)
            ->get(['lesson_id', 'completed_at']);
        $completedIds = $completedProgress->pluck('lesson_id');
        $activityCounts = $completedProgress->filter(fn ($progress) => $progress->completed_at !== null)
            ->countBy(fn ($progress) => $progress->completed_at->toDateString());
        $weeklyActivity = collect(range(6, 0))->map(function (int $daysAgo) use ($activityCounts): array {
            $date = today()->subDays($daysAgo);

            return ['date' => $date->toDateString(), 'label' => $date->format('d.m'), 'count' => (int) $activityCounts->get($date->toDateString(), 0)];
        });

        $assignments = Assignment::query()
            ->whereIn('course_id', $courseIds)
            ->whereDoesntHave('legalCase', fn ($query) => $query->where('status', '!=', 'published'))
            ->whereNotNull('deadline')
            ->where('deadline', '>=', now())
            ->orderBy('deadline')
            ->limit(5)
            ->get();

        return view('student.dashboard', [
            'enrollments' => $enrollments,
            'courseProgress' => $enrollments->mapWithKeys(function ($enrollment) use ($publishedLessons, $completedIds): array {
                $ids = $publishedLessons->filter(fn ($lesson) => $lesson->module->course_id === $enrollment->course_id)->pluck('id');
                $completed = $ids->intersect($completedIds)->count();

                return [$enrollment->course_id => ['total' => $ids->count(), 'completed' => $completed, 'percent' => $ids->isEmpty() ? 0 : (int) round($completed / $ids->count() * 100), 'next_lesson' => $publishedLessons->first(fn ($lesson) => $lesson->module->course_id === $enrollment->course_id && ! $completedIds->contains($lesson->id))]];
            }),
            'completedCount' => $completedIds->count(),
            'totalLessons' => $publishedLessonIds->count(),
            'assignments' => $assignments,
            'todaySessions' => ClassSchedule::query()->whereIn('course_id', $courseIds)
                ->whereDate('starts_at', today())->where(fn ($query) => $query->whereNull('group_id')->orWhereHas('group.members', fn ($members) => $members->where('users.id', $user->id)))
                ->orderBy('starts_at')->limit(10)->get(),
            'upcomingExams' => Quiz::query()->whereIn('course_id', $courseIds)->where('kind', 'exam')
                ->where(fn ($query) => $query->whereNull('available_until')->orWhere('available_until', '>', now()))->orderBy('available_from')->limit(5)->get(),
            'recentResults' => QuizAttempt::query()->where('student_id', $user->id)->where('status', '!=', 'in_progress')
                ->whereHas('quiz', fn ($query) => $query->whereIn('course_id', $courseIds))->with('quiz')->withCount(['answers as pending_answers_count' => fn ($query) => $query->whereNull('points_earned')])->latest('id')->limit(5)->get(),
            'unreadMessages' => $user->unreadNotifications()->count(),
            'mentorAssignments' => MentorAssignment::query()->where('student_id', $user->id)->whereIn('course_id', $courseIds)->with('mentor', 'course')->latest('id')->get(),
            'weeklyActivity' => $weeklyActivity,
        ]);
    }
}
