<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\MentorAssignment;
use App\Models\MentorReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MentorDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $mentor = $request->user();

        $studentIds = MentorAssignment::query()->where('mentor_id', $mentor->id)->pluck('student_id');

        $pending = AssignmentSubmission::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['submitted', 'resubmitted']);

        $stats = [
            'students' => $studentIds->unique()->count(),
            'pending' => (clone $pending)->count(),
            'reviewed_week' => MentorReview::query()
                ->where('mentor_id', $mentor->id)
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
            'revision' => AssignmentSubmission::query()
                ->whereIn('student_id', $studentIds)
                ->where('status', 'revision_requested')
                ->count(),
        ];

        $queue = (clone $pending)
            ->with(['assignment', 'student'])
            ->oldest('submitted_at')
            ->limit(5)
            ->get();

        $students = User::query()
            ->whereIn('id', $studentIds)
            ->orderBy('name')
            ->limit(6)
            ->get();

        $recent = MentorReview::query()
            ->where('mentor_id', $mentor->id)
            ->with(['submission.assignment', 'submission.student'])
            ->latest()
            ->limit(5)
            ->get();

        return view('mentor.dashboard', compact('stats', 'queue', 'students', 'recent'));
    }
}
