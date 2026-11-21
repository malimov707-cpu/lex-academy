<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LegalCase;
use App\Models\MentorAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LegalCaseController extends Controller
{
    public function index(Request $request, Course $course): View
    {
        $this->authorizeCourse($request, $course);

        return view('admin.courses.cases', [
            'course' => $course,
            'cases' => LegalCase::query()->whereHas('assignment', fn ($query) => $query->where('course_id', $course->id))
                ->with('assignment')->latest()->paginate(12),
            'categories' => LegalCase::categories(),
            'mentors' => User::query()->where('role', 'mentor')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($request, $course);
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $request, $course): void {
            $assignment = $course->assignments()->create([
                'teacher_id' => $request->user()->id, 'title' => $data['title'],
                'type' => 'legal_case', 'instructions' => $data['analysis_questions'],
                'rubric' => $data['rubric'] ?? null, 'max_score' => $data['max_score'],
                'deadline' => $data['deadline'] ?? null, 'submission_attempts' => $data['submission_attempts'],
                'allow_late_submission' => $request->boolean('allow_late_submission'),
            ]);
            if (! empty($data['mentor_id'])) {
                foreach ($course->enrollments()->where('status', 'active')->cursor() as $enrollment) {
                    MentorAssignment::query()->firstOrCreate([
                        'mentor_id' => $data['mentor_id'], 'student_id' => $enrollment->student_id, 'course_id' => $course->id,
                    ]);
                }
            }
            $assignment->legalCase()->create(collect($data)->only([
                'category', 'situation', 'facts', 'legal_issue', 'legislation', 'analysis_questions', 'status',
            ])->all());
        });

        return back()->with('status', __('Kazus yaratildi. Mentor kurs talabalariga biriktirilgan bo‘lishi kerak.'));
    }

    public function edit(Request $request, LegalCase $legalCase): View
    {
        $this->authorizeCourse($request, $legalCase->assignment->course);

        return view('admin.courses.case-edit', ['legalCase' => $legalCase, 'categories' => LegalCase::categories()]);
    }

    public function update(Request $request, LegalCase $legalCase): RedirectResponse
    {
        $this->authorizeCourse($request, $legalCase->assignment->course);
        $data = $this->validated($request);
        DB::transaction(function () use ($legalCase, $data, $request): void {
            $legalCase->assignment->update([
                'title' => $data['title'], 'instructions' => $data['analysis_questions'],
                'rubric' => $data['rubric'] ?? null, 'max_score' => $data['max_score'],
                'deadline' => $data['deadline'] ?? null, 'submission_attempts' => $data['submission_attempts'],
                'allow_late_submission' => $request->boolean('allow_late_submission'),
            ]);
            $legalCase->update(collect($data)->only([
                'category', 'situation', 'facts', 'legal_issue', 'legislation', 'analysis_questions', 'status',
            ])->all());
        });

        return redirect()->route('admin.courses.cases.index', $legalCase->assignment->course_id)->with('status', __('Kazus saqlandi.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'mentor_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'mentor')],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(LegalCase::categories()))],
            'situation' => ['required', 'string', 'max:8000'],
            'facts' => ['required', 'string', 'max:4000'],
            'legal_issue' => ['required', 'string', 'max:2000'],
            'legislation' => ['required', 'string', 'max:4000'],
            'analysis_questions' => ['required', 'string', 'max:4000'],
            'status' => ['required', 'in:draft,published,archived'],
            'rubric' => ['nullable', 'string', 'max:4000'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'deadline' => ['nullable', 'date'],
            'submission_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'allow_late_submission' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless($request->user()->isAdmin() || $course->isManagedBy($request->user()), 403);
    }
}
