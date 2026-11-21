<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizGradingService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function quizScenario(string $kind = 'quiz'): array
{
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);

    $quiz = Quiz::query()->create([
        'course_id' => $course->id, 'title' => 'Test '.Str::random(4), 'kind' => $kind,
        'max_attempts' => 2, 'pass_score_percent' => 60, 'shuffle_questions' => false,
    ]);

    // 1) single: A to'g'ri
    $q1 = Question::query()->create(['course_id' => $course->id, 'type' => 'single', 'text' => 'Q1?', 'points' => 1]);
    $q1->options()->create(['text' => 'A', 'is_correct' => true]);
    $q1->options()->create(['text' => 'B', 'is_correct' => false]);
    $quiz->questions()->attach($q1, ['sort_order' => 1]);

    // 2) multiple: A+C
    $q2 = Question::query()->create(['course_id' => $course->id, 'type' => 'multiple', 'text' => 'Q2?', 'points' => 1]);
    $q2->options()->create(['text' => 'A', 'is_correct' => true]);
    $q2->options()->create(['text' => 'B', 'is_correct' => false]);
    $q2->options()->create(['text' => 'C', 'is_correct' => true]);
    $quiz->questions()->attach($q2, ['sort_order' => 2]);

    // 3) short_answer
    $q3 = Question::query()->create(['course_id' => $course->id, 'type' => 'short_answer', 'text' => 'Q3?', 'explanation' => 'konstitutsiya', 'points' => 2]);
    $quiz->questions()->attach($q3, ['sort_order' => 3]);

    // 4) long_answer (manual)
    $q4 = Question::query()->create(['course_id' => $course->id, 'type' => 'long_answer', 'text' => 'Q4?', 'points' => 2]);
    $quiz->questions()->attach($q4, ['sort_order' => 4]);

    return compact('course', 'student', 'quiz', 'q1', 'q2', 'q3', 'q4');
}

it('grades single, multiple, short answer automatically', function () {
    ['quiz' => $quiz, 'student' => $student, 'q1' => $q1, 'q2' => $q2, 'q3' => $q3, 'q4' => $q4] = quizScenario();

    $attempt = QuizAttempt::query()->create(['quiz_id' => $quiz->id, 'student_id' => $student->id, 'attempt' => 1]);
    QuizAnswer::query()->create(['attempt_id' => $attempt->id, 'question_id' => $q1->id, 'selected_option_ids' => [$q1->options->where('text', 'A')->first()->id]]);
    QuizAnswer::query()->create(['attempt_id' => $attempt->id, 'question_id' => $q2->id, 'selected_option_ids' => [
        $q2->options->where('text', 'A')->first()->id, $q2->options->where('text', 'C')->first()->id,
    ]]);
    QuizAnswer::query()->create(['attempt_id' => $attempt->id, 'question_id' => $q3->id, 'text_answer' => 'Konstitutsiya']);
    QuizAnswer::query()->create(['attempt_id' => $attempt->id, 'question_id' => $q4->id, 'text_answer' => 'Uzun javob...']);

    $score = app(QuizGradingService::class)->grade($attempt);

    // 1+1+2 = 4 avtomatik to'g'ri; 2 ball manual = baholanmagan (totalga kiradi lekin earnedga kirmaydi)
    expect($score)->toBe(66.67)
        ->and($attempt->fresh()->status)->toBe('submitted')
        ->and($attempt->answers()->where('question_id', $q4->id)->first()->points_earned)->toBeNull();
});

it('marks wrong answers with zero', function () {
    ['quiz' => $quiz, 'student' => $student, 'q1' => $q1, 'q2' => $q2, 'q3' => $q3] = quizScenario();

    $attempt = QuizAttempt::query()->create(['quiz_id' => $quiz->id, 'student_id' => $student->id, 'attempt' => 1]);
    $bOption = $q1->options->where('text', 'B')->first();
    QuizAnswer::query()->create(['attempt_id' => $attempt->id, 'question_id' => $q1->id, 'selected_option_ids' => [$bOption->id]]);

    $score = app(QuizGradingService::class)->grade($attempt);

    expect($score)->toBe(0.0)
        ->and((bool) $attempt->answers()->first()->is_correct)->toBeFalse();
});

it('enforces attempt limit and availability window', function () {
    ['course' => $course, 'quiz' => $quiz, 'student' => $student] = quizScenario();

    actingAs($student)->post("/learn/courses/{$course->id}/quizzes/{$quiz->id}/start")->assertRedirect();

    $first = QuizAttempt::query()->where('student_id', $student->id)->first();
    $first->update(['status' => 'submitted']);

    actingAs($student)->post("/learn/courses/{$course->id}/quizzes/{$quiz->id}/start")->assertRedirect();

    $first->update(['status' => 'submitted']);
    QuizAttempt::query()->where('student_id', $student->id)->update(['status' => 'submitted']);
    // 2/2 ishlatildi — 3-urinish 403
    actingAs($student)->post("/learn/courses/{$course->id}/quizzes/{$quiz->id}/start")->assertForbidden();

    $quiz->update(['available_until' => now()->subDay()]);
    actingAs($student)->post("/learn/courses/{$course->id}/quizzes/{$quiz->id}/start")->assertForbidden();
});

it('supports auto-save recovery of answers', function () {
    ['course' => $course, 'quiz' => $quiz, 'student' => $student, 'q1' => $q1, 'q3' => $q3] = quizScenario();

    actingAs($student)->post("/learn/courses/{$course->id}/quizzes/{$quiz->id}/start")->assertRedirect();
    $attempt = QuizAttempt::query()->where('student_id', $student->id)->firstOrFail();

    actingAs($student)->postJson("/learn/courses/{$course->id}/quizzes/{$quiz->id}/autosave", [
        'answers' => [
            ['question_id' => $q1->id, 'selected_option_ids' => [$q1->options->first()->id]],
            ['question_id' => $q3->id, 'text_answer' => 'qisman yozilgan javob'],
        ],
    ])->assertOk();

    expect($attempt->answers()->count())->toBe(2)
        ->and($attempt->answers()->where('question_id', $q3->id)->first()->text_answer)->toBe('qisman yozilgan javob');
});

it('forbids non-enrolled student and wrong course quiz', function () {
    ['course' => $course, 'quiz' => $quiz] = quizScenario();

    actingAs(User::factory()->student()->create())
        ->post("/learn/courses/{$course->id}/quizzes/{$quiz->id}/start")->assertForbidden();

    $otherCourse = Course::factory()->published()->create();
    actingAs(User::factory()->student()->create())
        ->post("/learn/courses/{$otherCourse->id}/quizzes/{$quiz->id}/start")->assertNotFound();
});
