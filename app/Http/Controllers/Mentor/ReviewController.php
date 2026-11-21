<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\MentorAssignment;
use App\Models\MentorReview;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $visible = AssignmentSubmission::query();
        if (! $user->isAdmin()) {
            $visible->whereHas('assignment', fn ($query) => $query->whereExists(
                fn ($query) => $query->selectRaw('1')->from('mentor_assignments')
                    ->whereColumn('mentor_assignments.student_id', 'assignment_submissions.student_id')
                    ->whereColumn('mentor_assignments.course_id', 'assignments.course_id')
                    ->where('mentor_id', $user->id)
            ));
        }

        $pending = (clone $visible)
            ->whereIn('status', ['submitted', 'resubmitted', 'under_review'])
            ->with('assignment.course', 'student')
            ->orderBy('submitted_at')->paginate(20, ['*'], 'pending_page');

        $reviewed = (clone $visible)
            ->whereIn('status', ['approved', 'rejected', 'revision_requested', 'graded'])
            ->with('assignment.course', 'student')
            ->latest('updated_at')->paginate(20, ['*'], 'reviewed_page');

        return view('mentor.reviews', ['pending' => $pending, 'reviewed' => $reviewed]);
    }

    public function show(Request $request, AssignmentSubmission $submission): View
    {
        $this->authorizeSubmission($request->user(), $submission);

        return view('mentor.review-show', [
            'caseFeedback' => $submission->assignment->legalCase?->feedback()->where('student_id', $submission->student_id)->latest()->limit(10)->get(),
            'submission' => $submission->load('assignment.course', 'assignment.legalCase', 'student', 'files', 'reviews.mentor'),
        ]);
    }

    public function claim(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $this->authorizeSubmission($request->user(), $submission);
        abort_unless(in_array($submission->status, ['submitted', 'resubmitted', 'under_review'], true), 409);
        $submission->update(['status' => 'under_review']);

        return back()->with('status', __('Tekshirish boshlandi.'));
    }

    public function storeReview(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeSubmission($user, $submission);

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'annotations' => ['nullable', 'string', 'max:10000'],
            'decision' => ['required', 'in:approved,rejected,revision_requested'],
        ]);

        $maxScore = $submission->assignment->max_score;
        abort_if($data['score'] > $maxScore, 422, "Ball {$maxScore} dan oshmasligi kerak");

        DB::transaction(function () use ($submission, $data, $user): void {
            MentorReview::query()->create([
                'submission_id' => $submission->id,
                'mentor_id' => $user->id,
                'score' => $data['score'],
                'comment' => $data['comment'] ?? null,
                'annotations' => isset($data['annotations']) ? ['notes' => $data['annotations']] : null,
                'decision' => $data['decision'],
            ]);

            $statusByDecision = [
                'approved' => 'approved',
                'rejected' => 'rejected',
                'revision_requested' => 'revision_requested',
            ];

            $submission->update([
                'status' => $statusByDecision[$data['decision']],
                'score' => $data['decision'] === 'revision_requested' ? $submission->score : $data['score'],
            ]);

        });

        return redirect()->route('mentor.reviews')->with('status', __('Baho saqlandi.'));
    }

    private function authorizeSubmission(User $user, AssignmentSubmission $submission): void
    {
        abort_if($submission->status === 'in_progress', 404);
        $assigned = MentorAssignment::query()
            ->where('mentor_id', $user->id)
            ->where('student_id', $submission->student_id)
            ->where('course_id', $submission->assignment->course_id)
            ->exists();

        abort_unless($assigned || $user->isAdmin(), 403);
    }
}
