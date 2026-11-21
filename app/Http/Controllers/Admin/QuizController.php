<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\EducationCategory;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(Request $request, Course $course): View
    {
        $this->authorizeCourse($request, $course);

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'difficulty' => ['nullable', 'integer', 'min:1', 'max:3'], 'category_id' => ['nullable', 'exists:education_categories,id']]);

        return view('admin.courses.quizzes', [
            'course' => $course->load(['quizzes.questions']),
            'categories' => EducationCategory::query()->orderBy('name')->get(),
            'bank' => $course->questions()->when($filters['search'] ?? null, fn ($query, $value) => $query->where('text', 'like', '%'.$value.'%'))
                ->when($filters['difficulty'] ?? null, fn ($query, $value) => $query->where('difficulty', $value))
                ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('category_id', $value))->orderByDesc('id')->get(),
            'quizzes' => $course->quizzes()->with('questions')->get(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($request, $course);
        $data = $request->validate([
            'question_ids' => ['nullable', 'array', 'max:200'],
            'question_ids.*' => [Rule::exists('questions', 'id')->where('course_id', $course->id)],
            'questions' => ['nullable', 'array', 'max:200'],
            'questions.*.text' => ['required', 'string', 'max:5000'],
            'questions.*.type' => ['required', 'in:single,multiple,true_false,short_answer,long_answer,scenario,legal_case,matching,ordering'],
            'questions.*.category_id' => ['nullable', 'exists:education_categories,id'],
            'questions.*.difficulty' => ['nullable', 'integer', 'min:1', 'max:3'],
            'shuffle_options' => ['nullable', 'boolean'], 'random_question_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'questions.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.explanation' => ['nullable', 'string', 'max:5000'],
            'questions.*.options' => ['nullable', 'array', 'max:30'],
            'questions.*.options.*' => ['nullable', 'string', 'max:2000'],
            'questions.*.correct' => ['nullable', 'array'],
            'questions.*.correct.*' => ['integer', 'min:0', 'max:29'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lesson_id' => ['nullable', Rule::exists('lessons', 'id')->whereIn('module_id', $course->modules()->pluck('id'))],
            'kind' => ['required', 'in:quiz,exam'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'pass_score_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'shuffle_questions' => ['nullable', 'boolean'],
        ]);

        $data['shuffle_options'] = $request->boolean('shuffle_options');
        $data['shuffle_questions'] = $request->boolean('shuffle_questions');

        $questions = $data['questions'] ?? [];
        $bankIds = $data['question_ids'] ?? [];
        if ($questions === [] && $bankIds === []) {
            throw ValidationException::withMessages(['questions' => __('Kamida bitta savol qo‘shing.')]);
        }
        foreach ($questions as $question) {
            $type = $question['type'];
            $options = array_filter($question['options'] ?? [], fn ($value) => trim((string) $value) !== '');
            $correct = array_unique($question['correct'] ?? []);
            if (in_array($type, ['single', 'true_false', 'multiple'], true)
                && (count($options) < 2 || count($correct) < 1 || (in_array($type, ['single', 'true_false'], true) && count($correct) !== 1)
                    || array_diff($correct, array_keys($options)) !== [])) {
                throw ValidationException::withMessages(['questions' => __('Variantlar va to‘g‘ri javoblarni belgilang.')]);
            }
            if ($type === 'short_answer' && trim($question['explanation'] ?? '') === '') {
                throw ValidationException::withMessages(['questions' => __('Qisqa javob uchun kutilayotgan javobni kiriting.')]);
            }
            if (in_array($type, ['matching', 'ordering'], true)) {
                $expected = json_decode($question['explanation'] ?? '', true);
                if (! is_array($expected) || $expected === [] || ($type === 'ordering' && ! array_is_list($expected))) {
                    throw ValidationException::withMessages(['questions' => __('Moslashtirish va tartiblash uchun to‘g‘ri JSON javob kiriting.')]);
                }
            }
        }
        if (($data['random_question_count'] ?? 0) > count($questions) + count(array_unique($bankIds))) {
            throw ValidationException::withMessages(['random_question_count' => __('Savollar soni bankdagi savollardan oshmasligi kerak.')]);
        }
        unset($data['questions'], $data['question_ids']);
        $quiz = DB::transaction(function () use ($course, $data, $questions, $bankIds): Quiz {
            $quiz = $course->quizzes()->create($data);

            // Savollar tanlash (variantlar bilan)
            $quiz->questions()->syncWithPivotValues($bankIds, ['sort_order' => 0]);
            foreach ($questions as $index => $q) {
                if (empty($q['text'])) {
                    continue;
                }

                $question = $course->questions()->create([
                    'type' => in_array($q['type'] ?? '', ['single', 'multiple', 'true_false', 'short_answer', 'long_answer', 'scenario', 'legal_case', 'matching', 'ordering'], true) ? $q['type'] : 'single',
                    'text' => $q['text'],
                    'explanation' => $q['explanation'] ?? null, 'category_id' => $q['category_id'] ?? $course->category_id, 'difficulty' => $q['difficulty'] ?? 1,
                    'points' => max(1, (int) ($q['points'] ?? 1)),
                ]);

                $correctIndexes = array_map('intval', (array) ($q['correct'] ?? []));

                foreach ((array) ($q['options'] ?? []) as $oi => $optionText) {
                    if (trim((string) $optionText) === '') {
                        continue;
                    }

                    $question->options()->create([
                        'text' => $optionText,
                        'is_correct' => in_array($oi, $correctIndexes, true),
                    ]);
                }

                $quiz->questions()->attach($question->id, ['sort_order' => $index + 1]);
            }

            return $quiz;
        });

        return back()->with('status', 'Test/imtihon yaratildi ('.$quiz->questions()->count().' savol).');
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless($request->user()->isAdmin() || $course->isManagedBy($request->user()), 403);
    }

    public function destroy(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeCourse($request, $quiz->course);
        $quiz->delete();

        return back()->with('status', __('O\'chirildi.'));
    }
}
