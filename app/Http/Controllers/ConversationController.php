<?php

namespace App\Http\Controllers;

use App\Models\CourseEnrollment;
use App\Models\MentorAssignment;
use App\Models\MentorConversation;
use App\Notifications\AcademyNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $assignments = MentorAssignment::query()->where($user->role->value === 'mentor' ? 'mentor_id' : 'student_id', $user->id)
            ->whereHas('course', fn ($query) => $query->where('status', 'published'))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('course_enrollments')
                ->whereColumn('course_enrollments.course_id', 'mentor_assignments.course_id')->whereColumn('course_enrollments.student_id', 'mentor_assignments.student_id')->where('status', 'active'))
            ->with('course', 'mentor', 'student')->latest('id')->paginate(20);

        return view('communication.conversations', compact('assignments'));
    }

    public function show(Request $request, MentorAssignment $assignment): View
    {
        $this->authorizeConversation($request, $assignment);
        $conversation = MentorConversation::query()->firstOrCreate(['mentor_assignment_id' => $assignment->id]);
        $conversation->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('communication.conversation', [
            'assignment' => $assignment->load('course', 'mentor', 'student'),
            'messages' => $conversation->messages()->with('sender')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function store(Request $request, MentorAssignment $assignment): RedirectResponse
    {
        $this->authorizeConversation($request, $assignment);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $conversation = MentorConversation::query()->firstOrCreate(['mentor_assignment_id' => $assignment->id]);
        $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $data['body']]);
        $recipient = $request->user()->id === $assignment->mentor_id ? $assignment->student : $assignment->mentor;
        $recipient->notify(new AcademyNotification('Yangi shaxsiy xabar', route('conversations.show', $assignment, false)));

        return back()->with('status', __('Xabar yuborildi.'));
    }

    private function authorizeConversation(Request $request, MentorAssignment $assignment): void
    {
        abort_unless(($request->user()->role->value === 'student' && $request->user()->id === $assignment->student_id) || ($request->user()->role->value === 'mentor' && $request->user()->id === $assignment->mentor_id), 403);
        abort_unless($assignment->course->status === 'published' && CourseEnrollment::query()->where('course_id', $assignment->course_id)
            ->where('student_id', $assignment->student_id)->where('status', 'active')->exists(), 403);
    }
}
