<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherDashboardOverview
{
    /** @return array<string, mixed> */
    public function forTeacher(User $teacher): array
    {
        $now = now();
        $periodStart = $now->copy()->startOfDay()->subDays(29);
        $managedCourses = Course::query()->managedBy($teacher);
        $courseIds = (clone $managedCourses)->select('id');

        $enrollments = CourseEnrollment::query()->whereIn('course_enrollments.course_id', $courseIds);
        $submissions = AssignmentSubmission::query()
            ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
            ->whereIn('assignments.course_id', $courseIds);
        $results = QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->whereIn('quizzes.course_id', $courseIds)
            ->where('quiz_attempts.status', '!=', 'in_progress')
            ->whereNotNull('score')
            ->whereDoesntHave('answers', fn (Builder $answers): Builder => $answers->whereNull('points_earned'));

        $courseCounts = (clone $managedCourses)->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(CASE WHEN status = 'published' THEN 1 END) AS published")
            ->selectRaw("COUNT(CASE WHEN status IN ('draft', 'review') THEN 1 END) AS drafts")
            ->selectRaw('COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) AS recent', [$periodStart, $now])
            ->first();

        $byStudent = (clone $enrollments)->groupBy('student_id')
            ->selectRaw("COUNT(*) AS enrollments, MAX(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active, MIN(enrolled_at) AS first_enrollment");
        $studentCounts = DB::query()->fromSub($byStudent, 'students')
            ->selectRaw('SUM(enrollments) AS total, SUM(active) AS active')
            ->selectRaw('COUNT(CASE WHEN active = 1 AND first_enrollment BETWEEN ? AND ? THEN 1 END) AS recent', [$periodStart, $now])
            ->first();

        $averages = (clone $results)->toBase()->selectRaw('AVG(score) AS overall')
            ->selectRaw('AVG(CASE WHEN submitted_at BETWEEN ? AND ? THEN score END) AS recent', [$periodStart, $now])
            ->selectRaw('AVG(CASE WHEN submitted_at >= ? AND submitted_at < ? THEN score END) AS previous', [$periodStart->copy()->subDays(30), $periodStart])
            ->first();
        $bestStudent = (clone $results)->join('users', 'users.id', '=', 'quiz_attempts.student_id')
            ->groupBy('users.id', 'users.name')->select('users.name')->selectRaw('AVG(score) AS average')
            ->orderByDesc('average')->orderBy('users.id')->toBase()->first();

        $activity = $this->learningActivity($teacher, $enrollments, $results);

        return [
            'courses' => $this->courseCards($managedCourses),
            'stats' => [
                'courses' => (int) $courseCounts->total,
                'published' => (int) $courseCounts->published,
                'students' => (int) $studentCounts->total,
                'pending' => (clone $submissions)->whereIn('assignment_submissions.status', ['submitted', 'resubmitted'])->count(),
                'active_students' => (int) $studentCounts->active,
                'average_score' => $averages->overall === null ? null : round((float) $averages->overall, 1),
                'best_score' => $bestStudent === null ? null : round((float) $bestStudent->average, 1),
            ],
            'metricDetails' => [
                'new_courses' => (int) $courseCounts->recent,
                'new_students' => (int) $studentCounts->recent,
                'score_change' => $averages->previous === null || $averages->recent === null ? null : round($averages->recent - $averages->previous, 1),
                'best_student' => $bestStudent?->name,
            ],
            'activityDays' => $this->activityDays($activity, $now),
            'recentActivity' => $this->recentActivity($activity, $submissions, $now),
            'recentSubmissions' => (clone $submissions)->select('assignment_submissions.*')->with(['assignment', 'student'])
                ->latest('submitted_at')->orderByDesc('assignment_submissions.id')->limit(6)->get(),
            'drafts' => (int) $courseCounts->drafts,
            'lessonsCount' => Lesson::query()->join('course_modules', 'course_modules.id', '=', 'lessons.module_id')
                ->whereIn('course_modules.course_id', $courseIds)->count(),
        ];
    }

    /** @return Collection<int, Course> */
    private function courseCards(Builder $managedCourses): Collection
    {
        $courses = (clone $managedCourses)->withCount(['enrollments', 'assignments', 'quizzes',
            'enrollments as active_students_count' => fn (Builder $query): Builder => $query->where('status', 'active'),
        ])->latest()->orderByDesc('id')->limit(6)->get();
        $lessons = Lesson::query()->join('course_modules', 'course_modules.id', '=', 'lessons.module_id')
            ->whereIn('course_modules.course_id', $courses->modelKeys());
        $lessonCounts = (clone $lessons)->groupBy('course_modules.course_id')->select('course_modules.course_id')
            ->selectRaw("COUNT(*) AS total, COUNT(CASE WHEN lessons.status = 'published' THEN 1 END) AS published")
            ->toBase()->get()->keyBy('course_id');
        $completions = $lessons->where('lessons.status', 'published')
            ->join('lesson_progress', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('course_enrollments', function (JoinClause $join): void {
                $join->on('course_enrollments.student_id', '=', 'lesson_progress.student_id')
                    ->on('course_enrollments.course_id', '=', 'course_modules.course_id');
            })
            ->where('lesson_progress.status', 'completed')->where('course_enrollments.status', 'active')
            ->groupBy('course_modules.course_id')->select('course_modules.course_id')
            ->selectRaw('COUNT(*) AS completed')->toBase()->pluck('completed', 'course_id');

        foreach ($courses as $course) {
            $counts = $lessonCounts->get($course->id);
            $possible = ($counts->published ?? 0) * $course->active_students_count;
            $course->lessons_count = (int) ($counts->total ?? 0);
            $course->progress_percent = $possible > 0 ? (int) round(100 * $completions->get($course->id, 0) / $possible) : null;
        }

        return $courses;
    }

    private function learningActivity(User $teacher, Builder $enrollments, Builder $results): QueryBuilder
    {
        $courtroom = SimulationSession::query()
            ->join('simulation_scenarios', 'simulation_scenarios.id', '=', 'simulation_sessions.scenario_id')
            ->where('simulation_scenarios.created_by', $teacher->id)->where('simulation_sessions.status', 'completed')
            ->selectRaw("'courtroom' AS type, simulation_sessions.id AS event_id, completed_at AS occurred_at, student_id, simulation_scenarios.title, NULL AS score");
        $courses = (clone $enrollments)->join('courses', 'courses.id', '=', 'course_enrollments.course_id')
            ->selectRaw("'enrollment' AS type, course_enrollments.id AS event_id, enrolled_at AS occurred_at, student_id, courses.title, NULL AS score");
        $quizzes = (clone $results)->selectRaw("'quiz' AS type, quiz_attempts.id AS event_id, submitted_at AS occurred_at, student_id, quizzes.title, quiz_attempts.score");

        return $quizzes->toBase()->unionAll($courtroom)->unionAll($courses);
    }

    /** @return list<array{date: string, courtroom: int, courses: int, quizzes: int}> */
    private function activityDays(QueryBuilder $activity, Carbon $now): array
    {
        $start = $now->copy()->startOfDay()->subDays(89);
        $counts = DB::query()->fromSub($activity, 'activity')->whereBetween('occurred_at', [$start, $now])
            ->selectRaw('DATE(occurred_at) AS activity_date, type, COUNT(*) AS total')->groupByRaw('DATE(occurred_at), type')
            ->get()->groupBy('activity_date');
        $days = [];
        for ($date = $start->copy(); $date->lte($now); $date->addDay()) {
            $key = $date->toDateString();
            $events = $counts->get($key, collect())->keyBy('type');
            $days[] = [
                'date' => $key,
                'courtroom' => (int) ($events->get('courtroom')->total ?? 0),
                'courses' => (int) ($events->get('enrollment')->total ?? 0),
                'quizzes' => (int) ($events->get('quiz')->total ?? 0),
            ];
        }

        return $days;
    }

    /** @return Collection<int, array{type: string, title: string, student: string, at: Carbon, score: ?float}> */
    private function recentActivity(QueryBuilder $activity, Builder $submissions, Carbon $now): Collection
    {
        $submittedWork = (clone $submissions)->where('assignment_submissions.status', '!=', 'in_progress')
            ->selectRaw("'submission' AS type, assignment_submissions.id AS event_id, submitted_at AS occurred_at, student_id, assignments.title, NULL AS score");

        return DB::query()->fromSub((clone $activity)->unionAll($submittedWork), 'activity')
            ->join('users', 'users.id', '=', 'activity.student_id')->where('occurred_at', '<=', $now)
            ->orderByDesc('occurred_at')->orderBy('type')->orderByDesc('event_id')->limit(12)
            ->get(['activity.type', 'activity.title', 'activity.occurred_at', 'activity.score', 'users.name'])
            ->map(fn (object $event): array => [
                'type' => $event->type,
                'title' => $event->title,
                'student' => $event->name,
                'at' => Carbon::parse($event->occurred_at),
                'score' => $event->score === null ? null : (float) $event->score,
            ]);
    }
}
