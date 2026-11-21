<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuizReviewController extends Controller
{
    public function index(Request $request, Quiz $quiz): View
    {
        abort_unless($request->user()->isAdmin() || $quiz->course->isManagedBy($request->user()), 403);

        return view('admin.courses.quiz-results', [
            'average' => round((float) $quiz->attempts()->where('status', '!=', 'in_progress')->whereDoesntHave('answers', fn ($query) => $query->whereNull('points_earned'))->avg('score'), 2),
            'quiz' => $quiz, 'attempts' => $quiz->attempts()->where('status', '!=', 'in_progress')
                ->with('student', 'answers.question')->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $attempt->quiz->course->isManagedBy($request->user()), 403);
        abort_if($attempt->status === 'in_progress', 409);
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'numeric', 'min:0']]);
        DB::transaction(function () use ($data, $attempt): void {
            $attempt = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $answers = $attempt->answers()->with('question')->get()->keyBy('id');
            foreach ($data['scores'] as $id => $score) {
                $answer = $answers->get($id);
                if (! $answer || ! in_array($answer->question->type, ['long_answer', 'scenario', 'legal_case'], true) || $score > $answer->question->points) {
                    throw ValidationException::withMessages(['scores' => __('Ball yoki javob noto‘g‘ri.')]);
                }
                $answer->update(['points_earned' => $score]);
            }
            $earned = $attempt->answers()->sum('points_earned');
            $total = $attempt->selectedQuestions()->sum('points');
            $pending = $attempt->answers()->whereNull('points_earned')->exists();
            $attempt->update(['score' => $total ? round($earned / $total * 100, 2) : 0, 'status' => $pending ? 'submitted' : 'graded']);
        });

        return back()->with('status', __('Baholar saqlandi.'));
    }
}
