<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTutorMessage;
use App\Models\AiTutorSession;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\EducationAuditLog;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'until' => ['nullable', 'date', 'after_or_equal:from'], 'format' => ['nullable', 'in:csv']]);
        $from = $data['from'] ?? now()->subDays(30)->toDateString();
        $until = $data['until'] ?? now()->toDateString();
        $range = fn ($query) => $query->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $until);
        $stats = [
            'Talabalar' => User::query()->where('role', 'student')->count(),
            'Aktiv talabalar' => CourseEnrollment::query()->where('status', 'active')->distinct()->count('student_id'),
            'O‘qituvchilar' => User::query()->where('role', 'teacher')->count(),
            'Mentorlar' => User::query()->where('role', 'mentor')->count(),
            'Kurslar' => Course::query()->count(), 'Aktiv kurslar' => Course::query()->where('status', 'published')->count(),
            'Davrda tugallangan darslar' => $range(LessonProgress::query())->where('status', 'completed')->count(),
            'Davrda yuborilgan ishlar' => $range(AssignmentSubmission::query())->count(),
            'Tekshirilmagan ishlar' => AssignmentSubmission::query()->whereIn('status', ['submitted', 'resubmitted'])->count(),
            'Test o‘rtacha natijasi' => round((float) $range(QuizAttempt::query())->where('status', '!=', 'in_progress')->whereDoesntHave('answers', fn ($query) => $query->whereNull('points_earned'))->avg('score'), 2),
            'Davomat: qatnashgan' => $range(AttendanceRecord::query())->whereIn('status', ['present', 'late'])->count(),
            'Davomat: belgilangan' => $range(AttendanceRecord::query())->count(),
            'AI suhbatlari' => $range(AiTutorSession::query())->count(),
            'AI xabarlari' => $range(AiTutorMessage::query())->count(),
        ];
        $activity = AssignmentSubmission::query()->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $until)->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        if (($data['format'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($stats, $from, $until): void {
                $file = fopen('php://output', 'w');
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, [__('Ko‘rsatkich'), __('Qiymat'), __('Boshlanish'), __('Tugash')], escape: '');
                foreach ($stats as $label => $value) {
                    fputcsv($file, [__($label), $value, $from, $until], escape: '');
                }
                fclose($file);
            }, 'academy-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('admin.reports', ['stats' => $stats, 'activity' => $activity,
            'courses' => Course::query()->withCount('enrollments', 'assignments')->orderBy('title')->paginate(20),
            'students' => User::query()->where('role', 'student')->withCount(['enrollments', 'lessonProgress as completed_lessons_count' => fn ($query) => $query->where('status', 'completed')])->withAvg(['quizAttempts' => fn ($query) => $query->where('status', '!=', 'in_progress')->whereDoesntHave('answers', fn ($query) => $query->whereNull('points_earned'))], 'score')->orderBy('name')->limit(50)->get(),
            'aiModes' => AiTutorSession::query()->selectRaw('mode, COUNT(*) as total')->groupBy('mode')->get(),
            'logs' => EducationAuditLog::query()->with('user')->latest()->limit(50)->get(), 'from' => $from, 'until' => $until]);
    }
}
