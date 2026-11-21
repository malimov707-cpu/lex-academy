<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\QuizAttempt;
use App\Models\User;

use function Pest\Laravel\actingAs;

/** @return array<string, mixed> */
function quizSelectionScenario(string $kind = 'quiz'): array
{
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    $quiz = $course->quizzes()->create(['title' => 'Selection test', 'kind' => $kind, 'max_attempts' => 2]);
    for ($index = 1; $index <= 4; $index++) {
        $question = $course->questions()->create(['text' => "Question {$index}", 'type' => 'single', 'points' => $index]);
        $question->options()->createMany([['text' => 'Correct', 'is_correct' => true], ['text' => 'Wrong', 'is_correct' => false]]);
        $quiz->questions()->attach($question, ['sort_order' => $index]);
        if ($index === 1) {
            $q1 = $question;
        }
    }

    return compact('course', 'student', 'quiz', 'q1');
}

it('keeps the selected question and option order when an active exam is reopened', function () {
    ['course' => $course, 'quiz' => $quiz, 'student' => $student] = quizSelectionScenario('exam');
    $quiz->update(['random_question_count' => 2, 'shuffle_questions' => true, 'shuffle_options' => true]);
    $path = "/learn/courses/{$course->id}/quizzes/{$quiz->id}";
    actingAs($student)->post($path.'/start')->assertRedirect();
    $attempt = QuizAttempt::query()->sole();
    expect($attempt->question_order)->toHaveCount(2);
    $questions = $attempt->question_order;
    $options = $attempt->option_order;

    actingAs($student)->post($path.'/start')->assertRedirect();
    actingAs($student)->get($path)->assertOk()->assertViewHas('questions', fn ($rows) => $rows->pluck('id')->all() === $questions);

    expect(QuizAttempt::query()->count())->toBe(1);
    expect($attempt->fresh()->question_order)->toBe($questions);
    expect($attempt->fresh()->option_order)->toBe($options);
    foreach ($attempt->selectedQuestions() as $question) {
        expect($question->options->pluck('id')->all())->toBe($options[$question->id]);
    }
});

it('rejects answers to questions outside the attempt selection even inside the same quiz', function () {
    ['course' => $course, 'quiz' => $quiz, 'student' => $student] = quizSelectionScenario();
    $quiz->update(['random_question_count' => 1]);
    $path = "/learn/courses/{$course->id}/quizzes/{$quiz->id}";
    actingAs($student)->post($path.'/start')->assertRedirect();
    $attempt = QuizAttempt::query()->sole();
    $excluded = $quiz->questions()->whereNotIn('questions.id', $attempt->question_order)->firstOrFail();

    actingAs($student)->postJson($path.'/autosave', ['answers' => [['question_id' => $excluded->id, 'text_answer' => 'invalid']]])
        ->assertUnprocessable()->assertJsonValidationErrors('answers');

    expect($attempt->answers()->count())->toBe(0);
    expect($attempt->fresh()->status)->toBe('in_progress');
});

it('recovers saved subset answers and grades against selected points only', function () {
    ['course' => $course, 'quiz' => $quiz, 'student' => $student, 'q1' => $question] = quizSelectionScenario();
    $option = $question->options()->where('is_correct', true)->sole();
    $attempt = $quiz->attempts()->create(['student_id' => $student->id, 'attempt' => 1, 'status' => 'in_progress', 'started_at' => now(), 'question_order' => [$question->id], 'option_order' => [$question->id => $question->options->pluck('id')->all()]]);
    $path = "/learn/courses/{$course->id}/quizzes/{$quiz->id}";
    actingAs($student)->postJson($path.'/autosave', ['answers' => [['question_id' => $question->id, 'selected_option_ids' => [$option->id]]]])->assertOk()->assertJson(['saved' => true]);
    actingAs($student)->get($path)->assertOk()->assertViewHas('savedAnswers', fn ($answers) => $answers->get($question->id)?->selected_option_ids === [$option->id]);

    actingAs($student)->post($path.'/submit')->assertRedirect();

    expect((float) $attempt->fresh()->score)->toBe(100.0);
    expect($attempt->fresh()->status)->toBe('submitted');
});
