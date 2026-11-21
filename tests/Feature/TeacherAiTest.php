<?php

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\TeacherAiDraft;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('generates each teacher material type as a private draft', function (string $kind) {
    $course = Course::factory()->published()->create();
    config()->set('ai_tutor.api_key', 'test-key');
    config()->set('ai_tutor.base_url', 'https://ai.example.test/v1');
    Http::preventStrayRequests();
    Http::fake(['https://ai.example.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Tekshiriladigan AI materiali']]]])]);

    actingAs($course->teacher)->post(route('admin.teacher-ai.store', $course), ['kind' => $kind, 'topic' => 'Shartnoma', 'sources' => 'O‘qituvchi tasdiqlagan manba parchasi'])->assertRedirect();

    assertDatabaseHas('teacher_ai_drafts', ['course_id' => $course->id, 'kind' => $kind, 'status' => 'draft']);
    assertDatabaseCount('lesson_blocks', 0);
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'O‘qituvchi tasdiqlagan manba parchasi'));
    actingAs($course->teacher)->get(route('admin.teacher-ai.index', $course))->assertOk()->assertSee('Tekshiriladigan AI materiali');
})->with(['plan', 'notes', 'objectives', 'quiz', 'case', 'assignment', 'revision', 'rubric', 'summary']);

it('requires source verification and course scope before publishing an edited AI draft', function () {
    $course = Course::factory()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Dars']);
    $draft = TeacherAiDraft::query()->create(['course_id' => $course->id, 'teacher_id' => $course->teacher_id, 'kind' => 'notes', 'topic' => 'Shartnoma', 'sources' => 'Tekshirilgan manba', 'content' => 'AI qoralamasi']);
    $data = ['content' => 'O‘qituvchi tahrirlagan material', 'lesson_id' => $lesson->id];
    actingAs($course->teacher)->patch(route('admin.teacher-ai.approve', $draft), $data)->assertSessionHasErrors('sources_verified');
    actingAs(User::factory()->teacher()->create())->patch(route('admin.teacher-ai.approve', $draft), $data + ['sources_verified' => 1])->assertForbidden();

    actingAs($course->teacher)->patch(route('admin.teacher-ai.approve', $draft), $data + ['sources_verified' => 1])->assertRedirect();

    assertDatabaseHas('teacher_ai_drafts', ['id' => $draft->id, 'status' => 'approved', 'approved_by' => $course->teacher_id]);
    expect($lesson->blocks()->firstOrFail()->content['text'])->toContain('O‘qituvchi tahrirlagan material')->toContain('Tekshirilgan manba');
    actingAs($course->teacher)->patch(route('admin.teacher-ai.approve', $draft), $data + ['sources_verified' => 1])->assertConflict();
    assertDatabaseCount('lesson_blocks', 1);
});

it('does not save fake teacher material when AI is unavailable', function () {
    $course = Course::factory()->create();
    config()->set('ai_tutor.api_key', null);

    actingAs($course->teacher)->post(route('admin.teacher-ai.store', $course), ['kind' => 'plan', 'topic' => 'Mavzu', 'sources' => 'Manba'])->assertSessionHasErrors('ai');

    assertDatabaseCount('teacher_ai_drafts', 0);
});
