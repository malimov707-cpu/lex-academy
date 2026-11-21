<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $assignments = Assignment::query()
            ->whereIn('course_id', CourseEnrollment::query()->where('student_id', $user->id)->where('status', 'active')->select('course_id'))
            ->whereDoesntHave('legalCase', fn ($query) => $query->where('status', '!=', 'published'))
            ->whereHas('course', fn ($query) => $query->where('status', 'published'))
            ->with('course', 'legalCase')
            ->orderBy('deadline')
            ->get();

        $allSubmissions = AssignmentSubmission::query()
            ->where('student_id', $user->id)
            ->with('assignment', 'reviews.mentor', 'files')->orderBy('id')
            ->get();
        $submissions = $allSubmissions->keyBy('assignment_id');

        return view('student.assignments', [
            'assignments' => $assignments,
            'submissions' => $submissions, 'history' => $allSubmissions->groupBy('assignment_id'),
        ]);
    }

    public function draft(Request $request, Assignment $assignment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($assignment->course->status === 'published' && $user->enrollments()->where('course_id', $assignment->course_id)->where('status', 'active')->exists(), 403);
        if ($assignment->legalCase) {
            abort_unless($assignment->legalCase->status === 'published', 404);
        }
        $data = $request->validate(['text' => ['nullable', 'string', 'max:50000']]);
        DB::transaction(function () use ($assignment, $user, $data): void {
            $assignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $used = $assignment->submissions()->where('student_id', $user->id)->where('status', '!=', 'in_progress')->count();
            abort_if($used >= $assignment->submission_attempts, 403);
            $assignment->submissions()->updateOrCreate(['student_id' => $user->id, 'status' => 'in_progress'],
                ['text' => $data['text'] ?? null, 'attempt' => $used + 1]);
        });

        return back()->with('status', __('Matn qoralamasi saqlandi.'));
    }

    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $user = $request->user();

        if ($assignment->legalCase) {
            abort_unless($assignment->legalCase->status === 'published' && $assignment->course->status === 'published', 404);
            abort_unless($user->enrollments()->where('course_id', $assignment->course_id)->where('status', 'active')->exists(), 403);
        }

        $enrolled = CourseEnrollment::query()
            ->where('student_id', $user->id)
            ->where('course_id', $assignment->course_id)
            ->where('status', 'active')
            ->exists();
        abort_unless($enrolled && $assignment->course->status === 'published', 403);

        DB::transaction(function () use ($request, $assignment, $user): void {
            $assignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $attemptsUsed = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $user->id)->where('status', '!=', 'in_progress')
                ->count();
            abort_if($attemptsUsed >= $assignment->submission_attempts, 403, __('Urinishlar soni tugadi'));

            $late = $assignment->deadline && now()->gt($assignment->deadline);
            abort_if($late && ! $assignment->allow_late_submission, 403, __('Deadline o\'tgan'));

            $formats = array_intersect($assignment->allowed_file_formats ?? ['pdf', 'doc', 'docx', 'txt', 'jpg', 'png'], ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png']);
            $maxSize = min(20480, max(1, $assignment->max_file_size_kb ?? 20480));
            $data = $request->validate([
                'text' => ['nullable', 'required_without:files', 'string', 'min:10', 'max:50000'],
                'files' => ['nullable', 'required_without:text', 'array', 'min:1', 'max:5'],
                'files.*' => ['file', 'max:'.$maxSize, 'mimes:'.implode(',', $formats)],
            ]);

            $draft = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $user->id)->where('status', 'in_progress')->first();
            $values = [
                'assignment_id' => $assignment->id,
                'student_id' => $user->id,
                'attempt' => $attemptsUsed + 1,
                'text' => $data['text'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ];
            if ($draft) {
                $draft->update($values);
                $submission = $draft;
            } else {
                $submission = AssignmentSubmission::query()->create($values);
            }

            foreach ($request->file('files', []) as $file) {
                $submission->files()->create([
                    'file_path' => $file->store('submissions', 'local'),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_kb' => (int) ($file->getSize() / 1024),
                ]);
            }

        });

        return back()->with('status', __('Ish yuborildi.'));
    }
}
