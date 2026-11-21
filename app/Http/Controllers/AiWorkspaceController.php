<?php

namespace App\Http\Controllers;

use App\Models\AiTutorSession;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->role->value === 'student') {
            $entries = Lesson::query()->inLearningOrder()->where('lessons.status', 'published')
                ->whereHas('module.course', fn ($query) => $query->where('status', 'published')->whereHas('enrollments', fn ($enrollments) => $enrollments->where('student_id', $user->id)->where('status', 'active')))
                ->with('module.course')->paginate(12);
        } elseif ($user->role->value === 'mentor') {
            $entries = AssignmentSubmission::query()->where('status', '!=', 'in_progress')
                ->whereHas('assignment', fn ($query) => $query->whereExists(fn ($assignments) => $assignments->selectRaw('1')->from('mentor_assignments')
                    ->whereColumn('mentor_assignments.student_id', 'assignment_submissions.student_id')
                    ->whereColumn('mentor_assignments.course_id', 'assignments.course_id')->where('mentor_id', $user->id)))
                ->with('assignment.course', 'student')->latest('id')->paginate(12);
        } else {
            $entries = Course::query()->when(! $user->isAdmin(), fn ($query) => $query->managedBy($user))->orderBy('title')->paginate(12);
        }

        $sessions = AiTutorSession::query()
            ->where('student_id', $user->id)
            ->with(['lesson:id,title', 'course:id,title'])
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(20)
            ->get();

        $sessionId = $request->query('session');
        $activeSession = null;

        if ($sessionId) {
            $activeSession = $sessions->firstWhere('id', (int) $sessionId);
        }

        if (! $activeSession && $sessions->isNotEmpty()) {
            $activeSession = $sessions->first();
        }

        if ($activeSession) {
            $activeSession->load('messages');
        }

        $modes = [
            'explain' => 'Tushuntirish',
            'quiz_mode' => 'Test',
            'practice' => 'Amaliyot',
            'document' => 'Hujjat tahlili',
            'summary' => 'Konspekt',
            'flashcards' => 'Flashcards',
            'exam_prep' => 'Imtihon',
            'study_plan' => 'Shaxsiy reja',
        ];

        return view('ai.index', [
            'entries' => $entries,
            'workspaceRole' => $user->role->value,
            'sessions' => $sessions,
            'activeSession' => $activeSession,
            'modes' => $modes,
        ]);
    }
}
