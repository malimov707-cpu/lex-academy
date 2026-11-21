<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/** @return array<string, mixed> */
function secureQuiz(): array
{
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    $quiz = $course->quizzes()->create(['title' => 'Xavfsiz test', 'max_attempts' => 1, 'time_limit_minutes' => 10]);
    $question = $course->questions()->create(['text' => 'Savol', 'type' => 'single', 'points' => 2]);
    $option = $question->options()->create(['text' => 'To‘g‘ri', 'is_correct' => true]);
    $quiz->questions()->attach($question);

    return compact('course', 'student', 'quiz', 'question', 'option');
}

it('saves final form answers before grading and resumes the same active attempt', function () {
    ['course' => $course, 'student' => $student, 'quiz' => $quiz, 'question' => $question, 'option' => $option] = secureQuiz();
    actingAs($student)->post(route('student.quiz.start', [$course, $quiz]))->assertRedirect();
    actingAs($student)->post(route('student.quiz.start', [$course, $quiz]))->assertRedirect();
    assertDatabaseCount('quiz_attempts', 1);

    actingAs($student)->post(route('student.quiz.submit', [$course, $quiz]), ['answers' => [$question->id => ['selected_option_ids' => [$option->id]]]])->assertRedirect();

    assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'student_id' => $student->id, 'score' => 100, 'status' => 'submitted']);
});

it('rejects foreign quiz questions and foreign answer options without saving', function () {
    ['course' => $course, 'student' => $student, 'quiz' => $quiz, 'question' => $question] = secureQuiz();
    $other = $course->questions()->create(['text' => 'Boshqa savol', 'type' => 'single']);
    $otherOption = $other->options()->create(['text' => 'Begona']);
    actingAs($student)->post(route('student.quiz.start', [$course, $quiz]))->assertRedirect();

    actingAs($student)->postJson(route('student.quiz.autosave', [$course, $quiz]), ['answers' => [['question_id' => $other->id, 'text_answer' => 'bad']]])->assertUnprocessable();
    actingAs($student)->postJson(route('student.quiz.autosave', [$course, $quiz]), ['answers' => [['question_id' => $question->id, 'selected_option_ids' => [$otherOption->id]]]])->assertUnprocessable();

    assertDatabaseCount('quiz_answers', 0);
});

it('checks course scope and enrollment on autosave and submission', function () {
    ['course' => $course, 'student' => $student, 'quiz' => $quiz] = secureQuiz();
    actingAs($student)->post(route('student.quiz.start', [$course, $quiz]))->assertRedirect();
    $other = Course::factory()->published()->create();
    CourseEnrollment::query()->create(['course_id' => $other->id, 'student_id' => $student->id]);
    actingAs($student)->postJson(route('student.quiz.autosave', [$other, $quiz]), ['answers' => []])->assertNotFound();
    actingAs($student)->post(route('student.quiz.submit', [$other, $quiz]))->assertNotFound();
    CourseEnrollment::query()->where('course_id', $course->id)->update(['status' => 'suspended']);
    actingAs($student)->post(route('student.quiz.submit', [$course, $quiz]))->assertForbidden();
    assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'status' => 'in_progress']);
});

it('grades only saved answers when the server time limit expires', function () {
    $this->freezeTime();
    ['course' => $course, 'student' => $student, 'quiz' => $quiz, 'question' => $question, 'option' => $option] = secureQuiz();
    actingAs($student)->post(route('student.quiz.start', [$course, $quiz]))->assertRedirect();
    $this->travel(11)->minutes();

    actingAs($student)->post(route('student.quiz.submit', [$course, $quiz]), ['answers' => [$question->id => ['selected_option_ids' => [$option->id]]]])->assertRedirect();

    assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'score' => 0, 'status' => 'submitted']);
    assertDatabaseCount('quiz_answers', 0);
});

it('restores saved selections and text after refresh', function () {
    ['course' => $course, 'student' => $student, 'quiz' => $quiz, 'question' => $question, 'option' => $option] = secureQuiz();
    actingAs($student)->post(route('student.quiz.start', [$course, $quiz]))->assertRedirect();
    actingAs($student)->postJson(route('student.quiz.autosave', [$course, $quiz]), ['answers' => [['question_id' => $question->id, 'selected_option_ids' => [$option->id]]]])->assertOk();

    actingAs($student)->get(route('student.quiz.show', [$course, $quiz]))->assertOk()->assertSee('checked', false);
});

it('allows the course teacher to grade manual answers and blocks other teachers', function () {
    ['course' => $course, 'student' => $student, 'quiz' => $quiz] = secureQuiz();
    $manual = $course->questions()->create(['text' => 'Kazus', 'type' => 'legal_case', 'points' => 2]);
    $quiz->questions()->attach($manual);
    actingAs($student)->post(route('student.quiz.start', [$course, $quiz]))->assertRedirect();
    actingAs($student)->post(route('student.quiz.submit', [$course, $quiz]), ['answers' => [$manual->id => ['text_answer' => 'Tahlil']]])->assertRedirect();
    $attempt = QuizAttempt::query()->firstOrFail();
    $answer = $attempt->answers()->firstOrFail();
    actingAs(User::factory()->teacher()->create())->post(route('admin.quizzes.grade', $attempt), ['scores' => [$answer->id => 2]])->assertForbidden();
    actingAs($course->teacher)->post(route('admin.quizzes.grade', $attempt), ['scores' => [$answer->id => 3]])->assertSessionHasErrors('scores');
    actingAs($course->teacher)->get(route('admin.quizzes.results', $quiz))->assertOk()->assertSee('Tahlil');

    actingAs($course->teacher)->post(route('admin.quizzes.grade', $attempt), ['scores' => [$answer->id => 2]])->assertRedirect();

    assertDatabaseHas('quiz_attempts', ['id' => $attempt->id, 'score' => 50, 'status' => 'graded']);
});

it('renders the quiz builder and creates validated question bank entries', function () {
    $course = Course::factory()->create();
    actingAs($course->teacher)->get(route('admin.courses.quizzes.index', $course))->assertOk();
    actingAs($course->teacher)->post(route('admin.courses.quizzes.store', $course), ['title' => 'Yangi', 'kind' => 'exam', 'max_attempts' => 2, 'pass_score_percent' => 60,
        'questions' => [['text' => 'Qisqa savol', 'type' => 'short_answer', 'explanation' => 'javob', 'points' => 2]],
    ])->assertRedirect();
    assertDatabaseCount('quizzes', 1);
    assertDatabaseHas('questions', ['course_id' => $course->id, 'type' => 'short_answer']);
});

it('grades matching and ordering answers using structured expected values', function () {
    ['course' => $course, 'student' => $student, 'quiz' => $quiz] = secureQuiz();
    $matching = $course->questions()->create(['text' => 'Moslang', 'type' => 'matching', 'explanation' => '{"a":"1","b":"2"}', 'points' => 1]);
    $ordering = $course->questions()->create(['text' => 'Tartiblang', 'type' => 'ordering', 'explanation' => '["a","b"]', 'points' => 1]);
    $quiz->questions()->attach([$matching->id, $ordering->id]);
    actingAs($student)->post(route('student.quiz.start', [$course, $quiz]))->assertRedirect();

    actingAs($student)->post(route('student.quiz.submit', [$course, $quiz]), ['answers' => [$matching->id => ['text_answer' => '{"b":"2","a":"1"}'], $ordering->id => ['text_answer' => '["b","a"]']]])->assertRedirect();

    assertDatabaseHas('quiz_answers', ['question_id' => $matching->id, 'points_earned' => 1]);
    assertDatabaseHas('quiz_answers', ['question_id' => $ordering->id, 'points_earned' => 0]);
});

it('stores AI manual evaluation guidance without changing the official grade', function () {
    ['course' => $course, 'student' => $student, 'quiz' => $quiz] = secureQuiz();
    $question = $course->questions()->create(['text' => 'Huquqiy tahlil', 'type' => 'long_answer', 'points' => 2]);
    $quiz->questions()->attach($question);
    $attempt = $quiz->attempts()->create(['student_id' => $student->id, 'status' => 'submitted', 'score' => 0]);
    $answer = $attempt->answers()->create(['question_id' => $question->id, 'text_answer' => 'Talaba tahlili']);
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Dalillarni tekshiring.']]]])]);

    actingAs($course->teacher)->post(route('admin.quizzes.guidance', $answer))->assertRedirect();

    Http::assertSentCount(1);
    assertDatabaseHas('quiz_answers', ['id' => $answer->id, 'ai_guidance' => 'Dalillarni tekshiring.', 'points_earned' => null]);
    expect((float) $attempt->fresh()->score)->toBe(0.0);
});
