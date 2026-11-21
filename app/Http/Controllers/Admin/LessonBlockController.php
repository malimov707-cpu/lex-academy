<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Services\EducationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LessonBlockController extends Controller
{
    public function update(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizeLesson($request, $lesson);
        if ($request->boolean('blocks_present') && ! $request->has('blocks')) {
            $request->merge(['blocks' => []]);
        }
        $data = $request->validate([
            'blocks' => ['present', 'array', 'max:100'],
            'blocks.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('lesson_blocks', 'id')->where('lesson_id', $lesson->id)],
            'blocks.*.type' => ['required', Rule::in(['text', 'heading', 'quote', 'legal_citation', 'image', 'video', 'audio', 'document', 'interactive_question', 'quiz', 'assignment'])],
            'blocks.*.content' => ['required', 'array:text,url,material_id,resource_id,answer'],
            'blocks.*.content.text' => ['nullable', 'string', 'max:10000'],
            'blocks.*.content.url' => ['nullable', 'url:http,https', 'max:2000'],
            'blocks.*.content.material_id' => ['nullable', 'integer', Rule::exists('lesson_materials', 'id')->where('lesson_id', $lesson->id)],
            'blocks.*.content.resource_id' => ['nullable', 'integer'],
            'blocks.*.content.answer' => ['nullable', 'string', 'max:1000'],
        ]);
        $course = $lesson->module->course;
        foreach ($data['blocks'] as $block) {
            $content = $block['content'];
            if (in_array($block['type'], ['text', 'heading', 'quote', 'legal_citation', 'interactive_question'], true) && trim($content['text'] ?? '') === '') {
                throw ValidationException::withMessages(['blocks' => __('Blok matnini kiriting.')]);
            }
            if ($block['type'] === 'interactive_question' && trim($content['answer'] ?? '') === '') {
                throw ValidationException::withMessages(['blocks' => __('Kutilayotgan javobni kiriting.')]);
            }
            if (in_array($block['type'], ['image', 'video', 'audio', 'document'], true) && empty($content['url']) && empty($content['material_id'])) {
                throw ValidationException::withMessages(['blocks' => __('Blok uchun material yoki havola tanlang.')]);
            }
            if (in_array($block['type'], ['quiz', 'assignment'], true)) {
                $query = $block['type'] === 'quiz' ? $course->quizzes() : $course->assignments();
                if (! $query->whereKey($content['resource_id'] ?? 0)->exists()) {
                    throw ValidationException::withMessages(['blocks' => __('Resurs shu kursga tegishli bo‘lishi kerak.')]);
                }
            }
        }
        DB::transaction(function () use ($request, $lesson, $data): void {
            $lesson = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            $lesson->versions()->create(['edited_by' => $request->user()->id, 'snapshot' => $lesson->only(['title', 'body', 'description', 'objectives', 'completion_criteria', 'completion_rules', 'status']) + ['blocks' => $lesson->blocks()->get()->toArray()]]);
            $savedIds = [];
            foreach ($data['blocks'] as $position => $block) {
                $values = ['type' => $block['type'], 'content' => $block['content'], 'sort_order' => $position + 1];
                if (! empty($block['id'])) {
                    $saved = $lesson->blocks()->findOrFail($block['id']);
                    if ($saved->content !== $block['content']) {
                        DB::table('lesson_block_responses')->where('lesson_block_id', $saved->id)->delete();
                    }
                    $saved->update($values);
                } else {
                    $saved = $lesson->blocks()->create($values);
                }
                $savedIds[] = $saved->id;
            }
            $lesson->blocks()->whereNotIn('id', $savedIds)->delete();
        });

        return back()->with('status', __('Dars bloklari saqlandi.'));
    }

    public function preview(Request $request, Lesson $lesson): View
    {
        $this->authorizeLesson($request, $lesson);

        return view('admin.courses.lesson-preview', ['lesson' => $lesson->load('blocks'), 'course' => $lesson->module->course, 'preview' => true, 'responses' => collect()]);
    }

    public function respond(Request $request, LessonBlock $block, EducationAccess $access): RedirectResponse
    {
        $access->learn($request->user(), $block->lesson->module->course, $block->lesson);
        abort_unless($block->type === 'interactive_question', 404);
        $data = $request->validate(['answer' => ['required', 'string', 'max:4000']]);
        $correct = mb_strtolower(trim($data['answer'])) === mb_strtolower(trim($block->content['answer'] ?? ''));
        DB::table('lesson_block_responses')->updateOrInsert(['lesson_block_id' => $block->id, 'student_id' => $request->user()->id],
            ['answer' => $data['answer'], 'is_correct' => $correct, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __($correct ? 'To‘g‘ri javob!' : 'Qayta o‘ylab ko‘ring.'));
    }

    public function reorder(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);
        $data = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['required', 'integer', 'distinct']]);
        $data['ids'] = array_map('intval', $data['ids']);
        DB::transaction(function () use ($data, $lesson, $request): void {
            $lesson = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            $actual = $lesson->blocks()->pluck('id')->sort()->values()->all();
            $requested = collect($data['ids'])->sort()->values()->all();
            abort_unless($actual === $requested, 422);
            $lesson->versions()->create(['edited_by' => $request->user()->id, 'snapshot' => $lesson->only(['title', 'body', 'description', 'objectives', 'completion_criteria', 'completion_rules', 'status']) + ['blocks' => $lesson->blocks()->get()->toArray()]]);
            foreach ($data['ids'] as $position => $id) {
                $lesson->blocks()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(['saved' => true]);
    }

    private function authorizeLesson(Request $request, Lesson $lesson): void
    {
        abort_unless($request->user()->isAdmin() || $lesson->module->course->isManagedBy($request->user()), 403);
    }
}
