<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherWorkspaceController extends Controller
{
    public function assessments(Request $request): View
    {
        $courses = Course::query()->managedBy($request->user())->withCount('assignments', 'quizzes')->with([
            'assignments' => fn ($query) => $query->withCount(['submissions as pending_submissions_count' => fn ($submissions) => $submissions->whereIn('status', ['submitted', 'resubmitted'])])->orderBy('title'),
            'quizzes' => fn ($query) => $query->withCount(['attempts as pending_attempts_count' => fn ($attempts) => $attempts->where('status', 'submitted')])->orderBy('title'),
        ])->orderBy('title')->get();
        $courseIds = $courses->modelKeys();

        $pendingSubmissions = AssignmentSubmission::query()->whereIn('status', ['submitted', 'resubmitted'])
            ->whereHas('assignment', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->with(['student:id,name', 'assignment:id,course_id,title'])->latest('submitted_at')->limit(30)->get();
        $pendingQuizzes = QuizAttempt::query()->where('status', 'submitted')
            ->whereHas('quiz', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->with(['student:id,name', 'quiz:id,course_id,title'])->latest('submitted_at')->limit(30)->get();

        return view('teacher.assessments', compact('courses', 'pendingSubmissions', 'pendingQuizzes'));
    }

    public function lessons(Request $request): View
    {
        $courses = Course::query()->managedBy($request->user())->with(['modules' => fn ($query) => $query->withCount('lessons')->orderBy('sort_order')])
            ->withCount('modules')->orderBy('title')->get();

        return view('teacher.lessons', compact('courses'));
    }
}
