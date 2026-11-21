<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

it('requires correct interactive answers before creating completion progress', function () {
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Interaktiv dars', 'status' => 'published', 'completion_rules' => ['require_interactive' => true]]);
    $block = $lesson->blocks()->create(['type' => 'interactive_question', 'content' => ['text' => 'Savol', 'answer' => 'Javob']]);
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    actingAs($student)->post(route('student.lesson.complete', [$course, $lesson]))->assertSessionHasErrors('completion');
    assertDatabaseCount('lesson_progress', 0);
    actingAs($student)->post(route('student.blocks.respond', $block), ['answer' => 'Javob'])->assertRedirect();

    actingAs($student)->post(route('student.lesson.complete', [$course, $lesson]))->assertRedirect();

    assertDatabaseCount('lesson_progress', 1);
});

it('does not accept unfinished or manually pending required quizzes', function () {
    $course = Course::factory()->published()->create();
    $quiz = $course->quizzes()->create(['title' => 'Majburiy test', 'pass_score_percent' => 60]);
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Dars', 'status' => 'published', 'completion_rules' => ['quiz_id' => $quiz->id]]);
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    actingAs($student)->post(route('student.lesson.complete', [$course, $lesson]))->assertSessionHasErrors('completion');
    $quiz->attempts()->create(['student_id' => $student->id, 'score' => 90, 'status' => 'in_progress']);
    actingAs($student)->post(route('student.lesson.complete', [$course, $lesson]))->assertSessionHasErrors('completion');
    $quiz->attempts()->where('student_id', $student->id)->update(['status' => 'graded']);

    actingAs($student)->post(route('student.lesson.complete', [$course, $lesson]))->assertRedirect();

    assertDatabaseCount('lesson_progress', 1);
});

it('rechecks interactive completion requirements before issuing a certificate for historical progress', function () {
    $course = Course::factory()->published()->create();
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Changed lesson', 'status' => 'published', 'completion_rules' => ['require_interactive' => true]]);
    $lesson->blocks()->create(['type' => 'interactive_question', 'content' => ['text' => 'Savol', 'answer' => 'Javob']]);
    $student = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    LessonProgress::query()->create(['lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed']);

    actingAs(User::factory()->academyAdmin()->create())->post(route('admin.certificates.store', $course), ['student_id' => $student->id])->assertSessionHasErrors('completion');

    assertDatabaseCount('certificates', 0);
});
