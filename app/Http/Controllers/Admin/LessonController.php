<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\LessonMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function index(CourseModule $module): View
    {
        return view('admin.courses.lessons', [
            'module' => $module->load('course'),
            'lessons' => $module->lessons()->withCount('materials')->get(),
        ]);
    }

    public function reorder(Request $request, CourseModule $module): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer', 'distinct']]);
        DB::transaction(function () use ($data, $module): void {
            $module = CourseModule::query()->lockForUpdate()->findOrFail($module->id);
            $ids = array_map('intval', $data['ids']);
            abort_unless($module->lessons()->pluck('id')->sort()->values()->all() === collect($ids)->sort()->values()->all(), 422);
            foreach ($ids as $position => $id) {
                $module->lessons()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(['saved' => true]);
    }

    public function store(Request $request, CourseModule $module): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
        ]);

        $module->lessons()->create($data + [
            'status' => 'draft',
            'sort_order' => $module->lessons()->count() + 1,
        ]);

        return back()->with('status', __('Dars yaratildi (draft).'));
    }

    public function edit(Lesson $lesson): View
    {
        return view('admin.courses.lesson-edit', [
            'blockTypes' => LessonBlock::types(),
            'quizzes' => $lesson->module->course->quizzes()->get(),
            'assignments' => $lesson->module->course->assignments()->get(),
            'lesson' => $lesson->load(['module.course', 'materials', 'blocks', 'versions']),
        ]);
    }

    public function update(Request $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'objectives' => ['nullable', 'string'],
            'completion_criteria' => ['nullable', 'string'],
            'require_interactive' => ['nullable', 'boolean'],
            'required_quiz_id' => ['nullable', Rule::exists('quizzes', 'id')->where('course_id', $lesson->module->course_id)],
            'required_assignment_id' => ['nullable', Rule::exists('assignments', 'id')->where('course_id', $lesson->module->course_id)],
        ]);

        DB::transaction(function () use ($lesson, $data, $request): void {
            $lesson->versions()->create(['edited_by' => $request->user()->id, 'snapshot' => $lesson->only(['title', 'description', 'body', 'objectives', 'completion_criteria', 'completion_rules', 'status'])]);
            if ($request->boolean('completion_rules_present')) {
                $lesson->completion_rules = ['require_interactive' => $request->boolean('require_interactive'), 'quiz_id' => $data['required_quiz_id'] ?? null, 'assignment_id' => $data['required_assignment_id'] ?? null];
            }
            $lesson->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? $lesson->description,
                'body' => $data['body'] ?? $lesson->body,
                'objectives' => $this->linesToArray($data['objectives'] ?? null),
                'completion_criteria' => $this->linesToArray($data['completion_criteria'] ?? null),
            ]);

        });

        return back()->with('status', __('Dars saqlandi.'));
    }

    public function publish(Lesson $lesson): RedirectResponse
    {
        $lesson->update(['status' => 'published']);

        return back()->with('status', __('Dars nashr qilindi.'));
    }

    public function moveUp(Lesson $lesson): RedirectResponse
    {
        $previous = Lesson::query()
            ->where('module_id', $lesson->module_id)
            ->where('sort_order', '<', $lesson->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($previous) {
            [$lesson->sort_order, $previous->sort_order] = [$previous->sort_order, $lesson->sort_order];
            $lesson->save();
            $previous->save();
        }

        return back();
    }

    public function moveDown(Lesson $lesson): RedirectResponse
    {
        $next = Lesson::query()
            ->where('module_id', $lesson->module_id)
            ->where('sort_order', '>', $lesson->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            [$lesson->sort_order, $next->sort_order] = [$next->sort_order, $lesson->sort_order];
            $lesson->save();
            $next->save();
        }

        return back();
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $lesson->delete();

        return back()->with('status', __('Dars o\'chirildi.'));
    }

    public function addMaterial(Request $request, Lesson $lesson): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:video,audio,pdf,docx,image,link,legal_doc'],
            'file' => ['nullable', 'required_without:external_url', 'file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,mp4,webm,mp3,wav,ogg'],
            'external_url' => ['nullable', 'required_without:file', 'url:http,https'],
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('lesson-materials', 'local');
        }

        LessonMaterial::query()->create([
            'lesson_id' => $lesson->id,
            'title' => $request->string('title'),
            'type' => $request->string('type'),
            'file_path' => $filePath,
            'external_url' => $filePath ? null : $request->input('external_url'),
        ]);

        return back()->with('status', __('Material qo\'shildi.'));
    }

    /**
     * @return list<string>|null
     */
    private function linesToArray(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode("\n", $value))));
    }
}
