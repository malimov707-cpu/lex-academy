<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseDiscussion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DiscussionController extends Controller
{
    public function index(Request $request, Course $course): View
    {
        $this->authorizeCourse($request->user(), $course);

        return view('communication.discussions', ['course' => $course, 'discussions' => CourseDiscussion::query()
            ->where('course_id', $course->id)->with('author')->withCount('replies')->latest('id')->paginate(20)]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $course);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:10000']]);
        $discussion = CourseDiscussion::query()->create($data + ['course_id' => $course->id, 'author_id' => $request->user()->id]);

        return redirect()->route('discussions.show', $discussion);
    }

    public function show(Request $request, CourseDiscussion $discussion): View
    {
        $this->authorizeCourse($request->user(), $discussion->course);

        return view('communication.discussion', ['discussion' => $discussion->load('author'),
            'canModerate' => $request->user()->isAdmin() || ($request->user()->role->value === 'teacher' && $discussion->course->isManagedBy($request->user())),
            'replies' => $discussion->replies()->with('author')->orderBy('id')->paginate(30)]);
    }

    public function reply(Request $request, CourseDiscussion $discussion): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $discussion->course);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($discussion, $data, $request): void {
            $discussion = CourseDiscussion::query()->lockForUpdate()->findOrFail($discussion->id);
            abort_if($discussion->is_locked, 409, __('Muhokama yopilgan.'));
            $discussion->replies()->create($data + ['author_id' => $request->user()->id]);
        });

        return back()->with('status', __('Javob saqlandi.'));
    }

    public function update(Request $request, CourseDiscussion $discussion): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->role->value === 'teacher' && $discussion->course->isManagedBy($request->user())), 403);
        $data = $request->validate(['is_locked' => ['required', 'boolean']]);
        $discussion->update($data);

        return back()->with('status', __('Muhokama holati yangilandi.'));
    }

    private function authorizeCourse(User $user, Course $course): void
    {
        if ($user->isAdmin() || ($user->role->value === 'teacher' && $course->isManagedBy($user))) {
            return;
        }
        abort_unless($course->status === 'published' && $user->role->value === 'student'
            && $course->enrollments()->where('student_id', $user->id)->where('status', 'active')->exists(), 403);
    }
}
