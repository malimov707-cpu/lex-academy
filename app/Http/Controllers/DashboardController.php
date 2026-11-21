<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AiTutorMessage;
use App\Models\AiTutorSession;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Rolga xos panellar mavjud bo'lsa — o'sha yerga yo'naltiramiz
        if ($user->hasRole(UserRole::Teacher)) {
            return redirect()->route('teacher.dashboard');
        }

        if ($user->hasRole(UserRole::Mentor)) {
            return redirect()->route('mentor.dashboard');
        }

        if ($user->hasRole(UserRole::Student)) {
            return redirect()->route('student.dashboard');
        }

        // Super admin — Control Center ko'rinishiga yo'naltiramiz
        if ($user->hasRole(UserRole::SuperAdmin)) {
            return redirect()->route('admin.control-center');
        }

        return view('dashboard', [
            'user' => $user,
            'stats' => $this->stats($user),
        ]);
    }

    /**
     * Super Admin — Control Center.
     */
    public function controlCenter(Request $request): View
    {
        $user = $request->user();
        $range = in_array((int) $request->query('range'), [7, 30, 90, 365], true) ? (int) $request->query('range') : 30;

        return view('admin.control-center', [
            'user' => $user,
            'range' => $range,
            'kpis' => $this->controlCenterKpis(),
            'chart' => $this->controlCenterChart($range),
            'activity' => $this->controlCenterActivity(),
            'modules' => $this->controlCenterModules(),
            'quickActions' => $this->controlCenterQuickActions(),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int, trend: float, icon: string, accent: string, spark: string}>
     */
    private function controlCenterKpis(): array
    {
        $usersTotal = User::count();
        $activeStudents = User::query()->where('role', UserRole::Student)->where('is_active', true)->count();
        $courses = Course::count();
        $sessionsTotal = SimulationSession::count();

        $thirtyDaysAgo = now()->subDays(30);
        $sixtyDaysAgo = now()->subDays(60);

        $calculateTrend = function (int $current, int $previous): float {
            if ($previous === 0) {
                return $current > 0 ? 100.0 : 0.0;
            }

            return round((($current - $previous) / $previous) * 100, 1);
        };

        $userTrend = $calculateTrend(
            User::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
            User::query()->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count()
        );

        $studentTrend = $calculateTrend(
            User::query()->where('role', UserRole::Student)->where('created_at', '>=', $thirtyDaysAgo)->count(),
            User::query()->where('role', UserRole::Student)->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count()
        );

        $courseTrend = $calculateTrend(
            Course::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
            Course::query()->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count()
        );

        $sessionTrend = $calculateTrend(
            SimulationSession::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
            SimulationSession::query()->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count()
        );

        $sparkFor = function (Builder $builder): string {
            $counts = [];
            for ($i = 11; $i >= 0; $i--) {
                $start = now()->subDays($i * 3 + 2)->startOfDay();
                $end = now()->subDays($i * 3)->endOfDay();
                $counts[] = (clone $builder)->whereBetween('created_at', [$start, $end])->count();
            }

            return $this->sparklinePath($counts);
        };

        return [
            [
                'label' => __('Jami foydalanuvchilar'),
                'value' => $usersTotal,
                'trend' => $userTrend,
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M21 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
                'accent' => 'blue',
                'spark' => $sparkFor(User::query()),
            ],
            [
                'label' => __('Faol talabalar'),
                'value' => $activeStudents,
                'trend' => $studentTrend,
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/></svg>',
                'accent' => 'purple',
                'spark' => $sparkFor(User::query()->where('role', UserRole::Student)),
            ],
            [
                'label' => __('Kurslar soni'),
                'value' => $courses,
                'trend' => $courseTrend,
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M4 19.5A2.5 2.5 0 016.5 17H20V4H6.5A2.5 2.5 0 004 6.5v13z"/><path d="M20 17v5H6.5a2.5 2.5 0 010-5H20z"/></svg>',
                'accent' => 'gold',
                'spark' => $sparkFor(Course::query()),
            ],
            [
                'label' => __('Virtual sud sessiyalari'),
                'value' => $sessionsTotal,
                'trend' => $sessionTrend,
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z"/></svg>',
                'accent' => 'teal',
                'spark' => $sparkFor(SimulationSession::query()),
            ],
        ];
    }

    /**
     * @return array{users: array<int, int>, students: array<int, int>, sessions: array<int, int>, labels: array<int, string>}
     */
    private function controlCenterChart(int $range): array
    {
        $buckets = max(2, (int) round($range / max(1, intdiv($range, 12))));
        $since = now()->subDays($range);

        $usersSeries = [];
        $studentsSeries = [];
        $sessionsSeries = [];
        $labels = [];

        $chunkSize = max(1, intdiv($range, $buckets));

        for ($i = $buckets - 1; $i >= 0; $i--) {
            $start = now()->subDays($i * $chunkSize + $chunkSize - 1)->startOfDay();
            $end = now()->subDays($i * $chunkSize)->endOfDay();

            $usersSeries[] = User::query()->whereBetween('created_at', [$start, $end])->count();
            $studentsSeries[] = User::query()->where('role', UserRole::Student)->whereBetween('created_at', [$start, $end])->count();
            $sessionsSeries[] = SimulationSession::query()->whereBetween('created_at', [$start, $end])->count();
            $labels[] = $start->format('d M');
        }

        return [
            'users' => $usersSeries,
            'students' => $studentsSeries,
            'sessions' => $sessionsSeries,
            'labels' => $labels,
        ];
    }

    /**
     * @return array<int, array{title: string, time: string, icon: string, accent: string}>
     */
    private function controlCenterActivity(): array
    {
        $items = [];

        $latestUsers = User::query()->latest()->limit(2)->get();
        foreach ($latestUsers as $latestUser) {
            $items[] = [
                'title' => __('Yangi ro‘yxatdan o‘tish: :name', ['name' => $latestUser->name]),
                'time' => $latestUser->created_at->diffForHumans(),
                'icon' => '👤',
                'accent' => 'blue',
            ];
        }

        $latestCourses = Course::query()->latest()->limit(1)->get();
        foreach ($latestCourses as $course) {
            $items[] = [
                'title' => __('Yangi kurs yuklandi: :title', ['title' => $course->title]),
                'time' => $course->created_at->diffForHumans(),
                'icon' => '📚',
                'accent' => 'purple',
            ];
        }

        $latestSessions = SimulationSession::query()->latest()->limit(2)->get();
        foreach ($latestSessions as $session) {
            $title = match ($session->status) {
                'completed' => __('Virtual sud sessiyasi yakunlandi'),
                'active' => __('Virtual sud sessiyasi boshlandi'),
                default => __('Yangi virtual sud mashg‘uloti yaratildi'),
            };
            $items[] = [
                'title' => $title,
                'time' => $session->created_at->diffForHumans(),
                'icon' => '⚖️',
                'accent' => 'gold',
            ];
        }

        return $items;
    }

    /**
     * @return array{overall: int, sections: array<int, array{label: string, value: int, accent: string}>}
     */
    private function controlCenterModules(): array
    {
        $totalUsers = max(1, User::count());
        $education = min(100, (int) round(Course::query()->where('status', 'published')->count() / max(1, Course::count()) * 100));
        $virtualCourt = min(100, (int) round(SimulationSession::query()->where('status', 'completed')->count() / max(1, SimulationSession::count()) * 100));

        $totalAiSessions = AiTutorSession::count();
        $activeAiSessions = AiTutorSession::query()->has('messages')->count();
        $aiMentor = $totalAiSessions > 0
            ? min(100, (int) round(($activeAiSessions / $totalAiSessions) * 100))
            : (AiTutorMessage::count() > 0 ? 100 : 0);

        $other = min(100, (int) round(User::query()->where('is_active', true)->count() / $totalUsers * 100));

        return [
            'overall' => (int) round(($education + $virtualCourt + $aiMentor + $other) / 4),
            'sections' => [
                ['label' => __('Ta’lim jarayoni'), 'value' => $education, 'accent' => 'blue'],
                ['label' => __('Virtual sud'), 'value' => $virtualCourt, 'accent' => 'purple'],
                ['label' => __('AI Ustoz'), 'value' => $aiMentor, 'accent' => 'gold'],
                ['label' => __('Boshqa modullar'), 'value' => $other, 'accent' => 'teal'],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, icon: string, accent: string}>
     */
    private function controlCenterQuickActions(): array
    {
        return [
            ['label' => __('Foydalanuvchilar'), 'url' => route('admin.users.index'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2"/><circle cx="10" cy="7" r="4"/></svg>', 'accent' => 'blue'],
            ['label' => __('Kurslar'), 'url' => route('admin.courses.index'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M4 19.5A2.5 2.5 0 016.5 17H20V4H6.5A2.5 2.5 0 004 6.5v13z"/></svg>', 'accent' => 'purple'],
            ['label' => __('Virtual sud ssenariylari'), 'url' => route('admin.courtroom.index'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M12 3v18M5 7l7-4 7 4"/></svg>', 'accent' => 'gold'],
            ['label' => __('Hisobotlar'), 'url' => route('admin.reports.index'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>', 'accent' => 'teal'],
            ['label' => __('Sozlamalar'), 'url' => route('admin.settings.edit'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33h0a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51h0a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82h0a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>', 'accent' => 'blue'],
            ['label' => __('Audit jurnali'), 'url' => route('admin.reports.index'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>', 'accent' => 'purple'],
        ];
    }

    /**
     * @param  array<int, int>  $values
     */
    private function sparklinePath(array $values): string
    {
        $max = max($values) ?: 1;
        $stepX = 120 / (count($values) - 1 ?: 1);

        return collect($values)->map(fn ($v, $i) => ($i === 0 ? 'M' : 'L').($i * $stepX).','.(38 - $v / $max * 34))->implode(' ');
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                'students' => User::query()->where('role', UserRole::Student)->count(),
                'teachers' => User::query()->where('role', UserRole::Teacher)->count(),
                'mentors' => User::query()->where('role', UserRole::Mentor)->count(),
                'courses' => Course::count(),
                'published_courses' => Course::query()->where('status', 'published')->count(),
                'pending_reviews' => AssignmentSubmission::query()->where('status', 'submitted')->count(),
            ];
        }

        if ($user->hasRole(UserRole::Teacher)) {
            return [
                'my_courses' => Course::query()->managedBy($user)->count(),
                'students' => CourseEnrollment::query()
                    ->whereIn('course_id', Course::query()->managedBy($user)->select('id'))
                    ->count(),
                'pending_reviews' => AssignmentSubmission::query()
                    ->whereIn('assignment_id', Assignment::query()->where('teacher_id', $user->id)->select('id'))
                    ->where('status', 'submitted')->count(),
            ];
        }

        if ($user->hasRole(UserRole::Mentor)) {
            return [
                'assigned_students' => $user->mentorAssignments()->distinct()->count('student_id'),
                'pending_reviews' => AssignmentSubmission::query()
                    ->whereHas('assignment', fn ($query) => $query->whereExists(fn ($query) => $query->selectRaw('1')->from('mentor_assignments')->whereColumn('mentor_assignments.student_id', 'assignment_submissions.student_id')->whereColumn('mentor_assignments.course_id', 'assignments.course_id')->where('mentor_id', $user->id)))
                    ->where('status', 'submitted')->count(),
            ];
        }

        $courseIds = CourseEnrollment::query()->where('student_id', $user->id)->select('course_id');

        $totalLessons = Lesson::query()
            ->where('status', 'published')
            ->whereIn('module_id', function ($query) use ($courseIds): void {
                $query->select('id')->from('course_modules')->whereIn('course_id', $courseIds);
            })->count();

        $completedLessons = LessonProgress::query()
            ->where('student_id', $user->id)
            ->where('status', 'completed')
            ->count();

        return [
            'my_courses' => $courseIds->count(),
            'completed_lessons' => $completedLessons,
            'learning_progress' => $totalLessons > 0 ? (int) round($completedLessons / $totalLessons * 100) : 0,
            'upcoming_deadlines' => Assignment::query()
                ->whereIn('course_id', $courseIds)
                ->whereNotNull('deadline')
                ->where('deadline', '>=', now())
                ->count(),
        ];
    }
}
