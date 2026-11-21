<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request, Course $course): View
    {
        abort_unless($request->user()->isAdmin() || $course->isManagedBy($request->user()), 403);

        return view('admin.courses.assignments', [
            'course' => $course->load('assignments.submissions'),
            'lessons' => $course->modules()->with('lessons')->get()->flatMap(fn ($m) => $m->lessons),
            'mentors' => User::query()->where('role', 'mentor')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $course->isManagedBy($request->user()), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'lesson_id' => ['nullable', Rule::exists('lessons', 'id')->whereIn('module_id', $course->modules()->pluck('id'))],
            'type' => ['required', 'in:written,legal_case,research,document_analysis,practical,presentation'],
            'deadline' => ['nullable', 'date', 'after:now'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'rubric' => ['nullable', 'string'],
            'submission_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'allow_late_submission' => ['nullable', 'boolean'],
            'allowed_file_formats' => ['nullable', 'array', 'min:1'],
            'allowed_file_formats.*' => ['required', 'in:pdf,doc,docx,txt,jpg,jpeg,png'],
            'max_file_size_kb' => ['nullable', 'integer', 'min:1', 'max:20480'],
            'mentor_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'mentor')],
        ]);

        $data['teacher_id'] = $request->user()->id;
        $data['allow_late_submission'] = $request->boolean('allow_late_submission');
        $data['rubric'] = $data['rubric'] ?? null;

        $mentorId = $data['mentor_id'] ?? null;
        unset($data['mentor_id']);

        $assignment = $course->assignments()->create($data);

        // Mentor biriktirilsa, kurs talabalariga mentor-assignment yozamiz
        if ($mentorId) {
            CourseEnrollment::query()
                ->where('course_id', $course->id)
                ->get()
                ->each(fn (CourseEnrollment $enrollment) => $assignment->course->getConnection()
                    ->table('mentor_assignments')->updateOrInsert(
                        ['mentor_id' => $mentorId, 'student_id' => $enrollment->student_id, 'course_id' => $course->id],
                        ['created_at' => now(), 'updated_at' => now()],
                    ));
        }

        return back()->with('status', __('Topshiriq yaratildi.'));
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $assignment->course->isManagedBy($request->user()), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'instructions' => ['nullable', 'string', 'max:20000'],
            'rubric' => ['nullable', 'string', 'max:10000'], 'deadline' => ['nullable', 'date'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'], 'submission_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'allow_late_submission' => ['nullable', 'boolean'], 'max_file_size_kb' => ['nullable', 'integer', 'min:1', 'max:20480'],
            'allowed_file_formats' => ['nullable', 'array', 'min:1'], 'allowed_file_formats.*' => ['required', 'in:pdf,doc,docx,txt,jpg,jpeg,png'],
        ]);
        $maxGiven = $assignment->submissions()->max('score');
        if ($maxGiven !== null && $data['max_score'] < $maxGiven) {
            throw ValidationException::withMessages(['max_score' => __('Maksimal ball mavjud bahodan past bo‘lishi mumkin emas.')]);
        }
        $mostUsed = $assignment->submissions()->where('status', '!=', 'in_progress')->selectRaw('student_id, COUNT(*) as total')->groupBy('student_id')->orderByDesc('total')->first()?->total ?? 0;
        if ($data['submission_attempts'] < $mostUsed) {
            throw ValidationException::withMessages(['submission_attempts' => __('Urinishlar limiti ishlatilgan urinishlardan kam bo‘lishi mumkin emas.')]);
        }
        $data['allow_late_submission'] = $request->boolean('allow_late_submission');
        $assignment->update($data);

        return back()->with('status', __('Topshiriq yangilandi.'));
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $assignment->course->isManagedBy($request->user()), 403);
        $assignment->delete();

        return back()->with('status', __('Topshiriq o\'chirildi.'));
    }
}
