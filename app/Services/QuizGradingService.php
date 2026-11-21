<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;

class QuizGradingService
{
    /**
     * Attemptni avtomatik baholaydi: single/multiple/true_false — to'liq;
     * short_answer katta-kichik harfga sezgir moslik; long_answer/scenario — manual (null).
     */
    public function grade(QuizAttempt $attempt): float
    {
        $attempt->load('answers.question.options', 'quiz.questions');

        $earned = 0.0;
        $total = 0.0;

        foreach ($attempt->selectedQuestions() as $question) {
            /** @var Question $question */
            $total += $question->points;
            $answer = $attempt->answers->firstWhere('question_id', $question->id);

            if (! $answer) {
                continue;
            }

            $correct = $this->isCorrect($question, $answer);

            if ($correct === null) {
                // manual baholanadi
                $answer->update(['is_correct' => null, 'points_earned' => null]);

                continue;
            }

            $answer->update([
                'is_correct' => $correct,
                'points_earned' => $correct ? $question->points : 0,
            ]);

            if ($correct) {
                $earned += $question->points;
            }
        }

        $score = $total > 0 ? round($earned / $total * 100, 2) : 0.0;

        $attempt->update([
            'score' => $score,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $score;
    }

    /**
     * @return bool|null null = manual grading talab qilinadi
     */
    private function isCorrect(Question $question, QuizAnswer $answer): ?bool
    {
        return match ($question->type) {
            'single', 'true_false' => $this->singleCorrect($question, $answer),
            'multiple' => $this->multipleCorrect($question, $answer),
            'short_answer' => $this->shortAnswerCorrect($question, $answer),
            'matching', 'ordering' => $this->structuredCorrect($question, $answer),
            'long_answer', 'scenario', 'legal_case' => null,
            default => null,
        };
    }

    private function structuredCorrect(Question $question, QuizAnswer $answer): bool
    {
        $expected = json_decode($question->explanation ?? '', true);
        $actual = json_decode($answer->text_answer ?? '', true);
        if (! is_array($expected) || ! is_array($actual) || $expected === []) {
            return false;
        }
        if ($question->type === 'matching') {
            ksort($expected);
            ksort($actual);
        }

        return $actual === $expected;
    }

    private function singleCorrect(Question $question, QuizAnswer $answer): bool
    {
        $selected = (array) ($answer->selected_option_ids ?? []);
        $correctId = $question->options->first(fn ($o) => $o->is_correct)?->id;

        return $selected === [$correctId] || (count($selected) === 1 && (int) $selected[0] === (int) $correctId);
    }

    private function multipleCorrect(Question $question, QuizAnswer $answer): bool
    {
        $selected = array_map('intval', (array) ($answer->selected_option_ids ?? []));
        sort($selected);

        $correct = $question->options->filter(fn ($o) => $o->is_correct)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $selected === $correct;
    }

    private function shortAnswerCorrect(Question $question, QuizAnswer $answer): bool
    {
        $expected = trim(mb_strtolower($question->explanation ?? ''));

        return trim(mb_strtolower($answer->text_answer ?? '')) === $expected;
    }
}
