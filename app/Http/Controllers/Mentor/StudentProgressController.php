<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\MentorAssignment;
use App\Services\LearningAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentProgressController extends Controller
{
    public function index(Request $request): View
    {
        return view('mentor.students', ['assignments' => MentorAssignment::query()
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('mentor_id', $request->user()->id))
            ->with('student', 'course')->latest('id')->paginate(20)]);
    }

    public function show(Request $request, MentorAssignment $assignment, LearningAnalytics $analytics): View
    {
        abort_unless($request->user()->isAdmin() || $assignment->mentor_id === $request->user()->id, 403);

        return view('student.analytics', ['course' => $assignment->course, 'student' => $assignment->student, 'mentorView' => true,
            'analytics' => $analytics->forCourse($assignment->course, $assignment->student)]);
    }
}
