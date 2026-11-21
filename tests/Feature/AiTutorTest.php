<?php

use App\Models\AiTutorMessage;
use App\Models\AiTutorSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

function tutorScenario(): array
{
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create([
        'module_id' => $module->id, 'title' => 'Konstitutsiya', 'status' => 'published',
        'body' => 'Konstitutsiya — davlatning asosiy qonuni.', 'sort_order' => 1,
    ]);
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);

    return compact('course', 'lesson', 'student');
}

it('returns 503 when AI is not configured instead of fake reply', function () {
    ['course' => $course, 'lesson' => $lesson, 'student' => $student] = tutorScenario();
    config()->set('ai_tutor.api_key', null);

    actingAs($student)
        ->postJson("/learn/courses/{$course->id}/lessons/{$lesson->id}/tutor", ['message' => 'Tushuntiring'])
        ->assertStatus(503)
        ->assertJsonStructure(['error']);

    // Soxta javob DBga yozilmagan
    expect(AiTutorMessage::query()->count())->toBe(1); // faqat user xabari
});

it('sends context to provider and stores assistant reply', function () {
    Http::fake([
        '*' => Http::response(['choices' => [['message' => ['content' => 'Konstitutsiya — asosiy qonun. Savol: ...']]], 200]),
    ]);
    ['course' => $course, 'lesson' => $lesson, 'student' => $student] = tutorScenario();
    config()->set('ai_tutor.api_key', 'test-key');

    actingAs($student)
        ->postJson("/learn/courses/{$course->id}/lessons/{$lesson->id}/tutor", ['message' => 'Tushuntiring'])
        ->assertOk()
        ->assertJsonStructure(['reply']);

    Http::assertSent(function ($request) use ($lesson): bool {
        $body = $request->data();

        return str_contains($body['messages'][0]['content'] ?? '', $lesson->title)
            && str_contains($body['messages'][0]['content'] ?? '', 'HUQUQ AI Academy');
    });

    expect(AiTutorMessage::query()->where('role', 'assistant')->count())->toBe(1);
});

it('isolates tutor sessions per student', function () {
    ['course' => $course, 'lesson' => $lesson, 'student' => $student] = tutorScenario();
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);

    actingAs($student)->postJson("/learn/courses/{$course->id}/lessons/{$lesson->id}/tutor", ['message' => 'salom'])->assertOk();

    $otherStudent = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $otherStudent->id]);

    actingAs($otherStudent)
        ->postJson("/learn/courses/{$course->id}/lessons/{$lesson->id}/tutor", ['message' => 'salom'])
        ->assertOk();

    $firstSession = AiTutorSession::query()->where('student_id', $student->id)->firstOrFail();
    $secondSession = AiTutorSession::query()->where('student_id', $otherStudent->id)->firstOrFail();

    expect($firstSession->id)->not->toBe($secondSession->id)
        ->and($firstSession->messages()->count())->toBe(2);
});

it('forbids tutor for non-enrolled student', function () {
    ['course' => $course, 'lesson' => $lesson] = tutorScenario();
    config()->set('ai_tutor.api_key', 'test-key');

    actingAs(User::factory()->student()->create())
        ->postJson("/learn/courses/{$course->id}/lessons/{$lesson->id}/tutor", ['message' => 'savol'])
        ->assertForbidden();
});

it('forbids tutor for mentors and teachers', function () {
    ['course' => $course, 'lesson' => $lesson] = tutorScenario();

    actingAs(User::factory()->mentor()->create())
        ->postJson("/learn/courses/{$course->id}/lessons/{$lesson->id}/tutor", ['message' => 'savol'])
        ->assertForbidden();
});

it('limits context messages sent to provider', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);
    ['course' => $course, 'lesson' => $lesson, 'student' => $student] = tutorScenario();
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.max_context_messages', 3);

    $session = AiTutorSession::query()->create(['student_id' => $student->id, 'lesson_id' => $lesson->id]);
    for ($i = 0; $i < 10; $i++) {
        AiTutorMessage::query()->create(['session_id' => $session->id, 'role' => 'user', 'content' => "msg $i"]);
    }

    actingAs($student)
        ->postJson("/learn/courses/{$course->id}/lessons/{$lesson->id}/tutor", ['message' => 'yangi savol'])
        ->assertOk();

    Http::assertSent(function ($request): bool {
        $messages = $request->data()['messages'] ?? [];

        return count($messages) === 4; // 3 tarix + 1 yangi
    });
});
