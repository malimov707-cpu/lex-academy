<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizGradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuizAttemptController extends Controller
{
    public function __construct(private QuizGradingService $grading) {}

    public function show(Request $request, Course $course, Quiz $quiz): View
    {
        $this->authorizeStudent($request->user(), $course, $quiz, false);

        $attemptsUsed = $quiz->attempts()->where('student_id', $request->user()->id)->count();
        $active = $quiz->attempts()
            ->where('student_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($active && $active->hasExpired()) {
            $this->grading->grade($active);
            $active = null;
        }

        $questions = $active ? $active->selectedQuestions() : collect();

        return view('student.quiz', [
            'course' => $course,
            'quiz' => $quiz,
            'questions' => $questions,
            'canStart' => (! $quiz->available_from || $quiz->available_from->lte(now())) && (! $quiz->available_until || $quiz->available_until->gt(now())),
            'expiresAt' => $active ? collect([$quiz->time_limit_minutes ? $active->started_at->copy()->addMinutes($quiz->time_limit_minutes)->timestamp : null, $quiz->available_until?->timestamp])->filter()->min() : null,
            'activeAttempt' => $active,
            'attemptsUsed' => $attemptsUsed,
            'savedAnswers' => $active?->answers()->get()->keyBy('question_id') ?? collect(),
            'results' => $quiz->attempts()->where('student_id', $request->user()->id)->where('status', '!=', 'in_progress')->with('answers.question')->latest()->get(),
        ]);
    }

    public function start(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        $this->authorizeStudent($request->user(), $course, $quiz);

        DB::transaction(function () use ($quiz, $request): void {
            $quiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);
            $active = $this->activeAttempt($request->user(), $quiz);
            if ($active) {
                if ($active->hasExpired()) {
                    $this->grading->grade($active);
                } else {
                    return;
                }
            }
            $used = $quiz->attempts()->where('student_id', $request->user()->id)->count();
            abort_if($used >= $quiz->max_attempts, 403, __('Urinishlar soni tugadi'));
            $questions = $quiz->questions()->with('options')->get();
            abort_if($questions->isEmpty(), 422);
            if ($quiz->shuffle_questions || $quiz->random_question_count) {
                $questions = $questions->shuffle();
            }
            if ($quiz->random_question_count) {
                $questions = $questions->take($quiz->random_question_count);
            }
            $optionOrder = $questions->mapWithKeys(fn ($question): array => [$question->id => ($quiz->shuffle_options ? $question->options->shuffle() : $question->options)->pluck('id')->all()])->all();
            $quiz->attempts()->create(['student_id' => $request->user()->id, 'attempt' => $used + 1, 'started_at' => now(),
                'question_order' => $questions->pluck('id')->all(), 'option_order' => $optionOrder]);
        });

        return redirect()->route('student.quiz.show', [$course, $quiz]);
    }

    /**
     * Auto-save: har bir savolni alohida saqlaydi (internet uzilsa tiklanadi).
     */
    public function autosave(Request $request, Course $course, Quiz $quiz): JsonResponse
    {
        $this->authorizeStudent($request->user(), $course, $quiz, false);
        $active = $this->activeAttempt($request->user(), $quiz);
        abort_unless($active, 404);
        if ($active->hasExpired()) {
            $this->grading->grade($active);

            return response()->json(['error' => 'Vaqt tugadi. Saqlangan javoblar baholandi.'], 409);
        }
        $rows = $this->validatedAnswers($request, $active);
        DB::transaction(function () use ($active, $rows): void {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($active->id);
            abort_unless($locked->status === 'in_progress' && ! $locked->hasExpired(), 409);
            $this->saveAnswers($locked, $rows);
        });

        return response()->json(['saved' => true]);
    }

    public function submit(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        $this->authorizeStudent($request->user(), $course, $quiz, false);
        $active = $this->activeAttempt($request->user(), $quiz);
        abort_unless($active, 404, __('Aktiv urinish yo‘q'));
        $rows = $active->hasExpired() ? [] : $this->validatedAnswers($request, $active);
        DB::transaction(function () use ($active, $rows): void {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($active->id);
            abort_unless($locked->status === 'in_progress', 409);
            if (! $locked->hasExpired()) {
                $this->saveAnswers($locked, $rows);
            }
            $this->grading->grade($locked);
        });
        $active->refresh();

        if ($active->answers()->whereNull('points_earned')->exists()) {
            return redirect()->route('student.quiz.show', [$course, $quiz])->with('status', __('Javoblar saqlandi. O‘qituvchi yakuniy bahosi kutilmoqda.'));
        }

        return redirect()
            ->route('student.quiz.show', [$course, $quiz])
            ->with('status', "Natija: {$active->score}%".($active->score >= $quiz->pass_score_percent ? ' — o\'tdingiz ✅' : ' — o\'tmadingiz ❌'));
    }

    /** @return list<array{question_id: int, selected_option_ids?: array|null, text_answer?: string|null}> */
    private function validatedAnswers(Request $request, QuizAttempt $attempt): array
    {
        $data = $request->validate([
            'answers' => ['sometimes', 'array', 'max:200'],
            'answers.*' => ['array'],
            'answers.*.question_id' => ['sometimes', 'integer'],
            'answers.*.selected_option_ids' => ['nullable', 'array', 'max:30'],
            'answers.*.selected_option_ids.*' => ['integer', 'distinct'],
            'answers.*.text_answer' => ['nullable', 'string', 'max:10000'],
        ]);
        $questions = $attempt->selectedQuestions()->keyBy('id');
        $rows = [];
        foreach ($data['answers'] ?? [] as $key => $row) {
            $id = (int) ($row['question_id'] ?? $key);
            $question = $questions->get($id);
            $selected = array_map('intval', $row['selected_option_ids'] ?? []);
            if (! $question || array_diff($selected, $question->options->pluck('id')->all()) !== []
                || (in_array($question->type, ['single', 'true_false'], true) && count($selected) > 1)) {
                throw ValidationException::withMessages(['answers' => __('Savol yoki variant bu testga tegishli emas.')]);
            }
            $rows[] = ['question_id' => $id, 'selected_option_ids' => $selected, 'text_answer' => $row['text_answer'] ?? null];
        }

        return $rows;
    }

    /** @param list<array{question_id: int, selected_option_ids?: array|null, text_answer?: string|null}> $rows */
    private function saveAnswers(QuizAttempt $attempt, array $rows): void
    {
        foreach ($rows as $row) {
            $attempt->answers()->updateOrCreate(['question_id' => $row['question_id']], [
                'selected_option_ids' => $row['selected_option_ids'], 'text_answer' => $row['text_answer'],
            ]);
        }
    }

    private function activeAttempt(User $student, Quiz $quiz): ?QuizAttempt
    {
        return $quiz->attempts()
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();
    }

    private function authorizeStudent(User $user, Course $course, Quiz $quiz, bool $checkWindow = true): void
    {
        abort_unless($quiz->course_id === $course->id, 404);

        abort_unless($course->status === 'published', 403);
        $now = now();
        abort_if($checkWindow && $quiz->available_from && $now->lt($quiz->available_from), 403, __('Hali ochilmagan'));
        abort_if($checkWindow && $quiz->available_until && $now->gt($quiz->available_until), 403, __('Yopilgan'));

        abort_unless(
            CourseEnrollment::query()->where('student_id', $user->id)->where('course_id', $course->id)->where('status', 'active')->exists(),
            403,
        );
    }
}
