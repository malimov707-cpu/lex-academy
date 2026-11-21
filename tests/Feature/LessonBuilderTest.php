<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

function editableLesson(): Lesson
{
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);

    return Lesson::query()->create(['module_id' => $module->id, 'title' => 'Blokli dars', 'status' => 'published']);
}

it('saves ordered lesson blocks with a version snapshot and previews them', function () {
    $lesson = editableLesson();
    $old = $lesson->blocks()->create(['type' => 'text', 'content' => ['text' => 'Oldingi matn'], 'sort_order' => 1]);

    actingAs($lesson->module->course->teacher)->put(route('admin.lessons.blocks.update', $lesson), ['blocks' => [
        ['type' => 'heading', 'content' => ['text' => 'Yangi sarlavha']],
        ['id' => $old->id, 'type' => 'text', 'content' => ['text' => 'Yangilangan matn']],
    ]])->assertRedirect();

    assertDatabaseHas('lesson_blocks', ['id' => $old->id, 'sort_order' => 2]);
    expect($lesson->versions()->firstOrFail()->snapshot['blocks'][0]['content']['text'])->toBe('Oldingi matn');
    actingAs($lesson->module->course->teacher)->get(route('admin.lessons.preview', $lesson))->assertOk()->assertSee('Yangi sarlavha')->assertSee('Yangilangan matn');
});

it('rejects unsafe URLs foreign material and foreign quiz references', function () {
    $lesson = editableLesson();
    $other = editableLesson();
    $material = $other->materials()->create(['type' => 'image', 'title' => 'Begona']);
    $quiz = $other->module->course->quizzes()->create(['title' => 'Begona test']);
    actingAs($lesson->module->course->teacher)->put(route('admin.lessons.blocks.update', $lesson), ['blocks' => [['type' => 'image', 'content' => ['url' => 'javascript:alert(1)']]]])->assertSessionHasErrors();
    actingAs($lesson->module->course->teacher)->put(route('admin.lessons.blocks.update', $lesson), ['blocks' => [['type' => 'image', 'content' => ['material_id' => $material->id]]]])->assertSessionHasErrors();
    actingAs($lesson->module->course->teacher)->put(route('admin.lessons.blocks.update', $lesson), ['blocks' => [['type' => 'quiz', 'content' => ['resource_id' => $quiz->id]]]])->assertSessionHasErrors('blocks');
    assertDatabaseCount('lesson_blocks', 0);
});

it('blocks unrelated teachers from editing or previewing a lesson', function () {
    $lesson = editableLesson();
    actingAs(User::factory()->teacher()->create())->get(route('admin.lessons.preview', $lesson))->assertForbidden();
    actingAs(User::factory()->teacher()->create())->put(route('admin.lessons.blocks.update', $lesson), ['blocks' => []])->assertForbidden();
    assertDatabaseCount('lesson_versions', 0);
});

it('allows only enrolled students to answer interactive questions without exposing expected answers', function () {
    $lesson = editableLesson();
    $block = $lesson->blocks()->create(['type' => 'interactive_question', 'content' => ['text' => 'Asosiy qonun nomi?', 'answer' => 'konstitutsiya'], 'sort_order' => 1]);
    $student = User::factory()->student()->create();
    actingAs($student)->post(route('student.blocks.respond', $block), ['answer' => 'Konstitutsiya'])->assertForbidden();
    CourseEnrollment::query()->create(['course_id' => $lesson->module->course_id, 'student_id' => $student->id]);
    actingAs($student)->get(route('student.lesson', [$lesson->module->course, $lesson]))->assertOk()->assertSee('Asosiy qonun nomi?')->assertDontSee('konstitutsiya');

    actingAs($student)->post(route('student.blocks.respond', $block), ['answer' => 'Konstitutsiya'])->assertRedirect();

    assertDatabaseHas('lesson_block_responses', ['lesson_block_id' => $block->id, 'student_id' => $student->id, 'is_correct' => 1]);
    assertDatabaseCount('lesson_progress', 0);
});

it('persists keyboard or drag ordering and rejects IDs outside the lesson', function () {
    $lesson = editableLesson();
    $one = $lesson->blocks()->create(['type' => 'text', 'content' => ['text' => 'One'], 'sort_order' => 1]);
    $two = $lesson->blocks()->create(['type' => 'text', 'content' => ['text' => 'Two'], 'sort_order' => 2]);
    actingAs($lesson->module->course->teacher)->postJson(route('admin.lessons.blocks.reorder', $lesson), ['ids' => [$two->id, $one->id]])->assertOk();
    expect($lesson->blocks()->first()->id)->toBe($two->id);
    actingAs($lesson->module->course->teacher)->postJson(route('admin.lessons.blocks.reorder', $lesson), ['ids' => [$two->id, 99999]])->assertUnprocessable();
    expect($lesson->blocks()->first()->id)->toBe($two->id);
});

it('supports removing every block through the editor form', function () {
    $lesson = editableLesson();
    $lesson->blocks()->create(['type' => 'text', 'content' => ['text' => 'Remove']]);

    actingAs($lesson->module->course->teacher)->put(route('admin.lessons.blocks.update', $lesson), ['blocks_present' => '1'])->assertRedirect();

    assertDatabaseCount('lesson_blocks', 0);
    assertDatabaseCount('lesson_versions', 1);
});

it('reorders lessons only within the teachers assigned module', function () {
    $one = editableLesson();
    $two = Lesson::query()->create(['module_id' => $one->module_id, 'title' => 'Ikkinchi dars', 'sort_order' => 2]);
    actingAs($one->module->course->teacher)->postJson(route('admin.lessons.reorder', $one->module), ['ids' => [$two->id, $one->id]])->assertOk();
    expect($one->module->lessons()->first()->id)->toBe($two->id);
    actingAs(User::factory()->teacher()->create())->postJson(route('admin.lessons.reorder', $one->module), ['ids' => [$one->id, $two->id]])->assertForbidden();
    actingAs($one->module->course->teacher)->postJson(route('admin.lessons.reorder', $one->module), ['ids' => [$one->id, 9999]])->assertUnprocessable();
    expect($one->module->lessons()->first()->id)->toBe($two->id);
});
