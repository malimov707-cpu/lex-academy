<?php

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\MentorAssignment;
use App\Models\TeacherAssignment;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('offers the AI workspace to every authenticated academy role', function (string $role) {
    actingAs(User::factory()->create(['role' => $role]))->get(route('ai.index'))->assertOk()
        ->assertSee('ai-tutor-mascot.png')->assertSee(__('AI Ustozga xush kelibsiz'));
})->with(['super_admin', 'academy_admin', 'teacher', 'mentor', 'student']);

it('redirects guests to login before opening the AI workspace', function () {
    $this->get(route('ai.index'))->assertRedirect(route('login'));
});

it('shows students only published lessons in their active published courses', function () {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $course->enrollments()->create(['student_id' => $student->id]);
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'My AI lesson', 'status' => 'published']);
    Lesson::query()->create(['module_id' => $module->id, 'title' => 'Private draft', 'status' => 'draft']);
    $otherModule = CourseModule::factory()->create();
    Lesson::query()->create(['module_id' => $otherModule->id, 'title' => 'Foreign lesson', 'status' => 'published']);

    actingAs($student)->get(route('ai.index'))->assertOk()->assertSee('My AI lesson')
        ->assertSee(route('student.tutor', [$course, $lesson]), false)->assertDontSee('Private draft')->assertDontSee('Foreign lesson');
});

it('shows only managed courses to teachers including additional assignments', function () {
    $teacher = User::factory()->teacher()->create();
    $own = Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Managed course']);
    $assigned = Course::factory()->create(['title' => 'Co-taught course']);
    TeacherAssignment::query()->create(['teacher_id' => $teacher->id, 'course_id' => $assigned->id]);
    Course::factory()->create(['title' => 'Foreign course']);

    actingAs($teacher)->get(route('ai.index'))->assertOk()->assertSee($own->title)->assertSee($assigned->title)->assertDontSee('Foreign course');
});

it('limits mentor AI entries to assigned students and courses and hides drafts', function () {
    $mentor = User::factory()->mentor()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $assignment = $course->assignments()->create(['teacher_id' => $course->teacher_id, 'title' => 'My review']);
    MentorAssignment::query()->create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'course_id' => $course->id]);
    $submission = $assignment->submissions()->create(['student_id' => $student->id, 'status' => 'submitted']);
    $assignment->submissions()->create(['student_id' => $student->id, 'status' => 'in_progress', 'attempt' => 2]);
    $assignment->submissions()->create(['student_id' => User::factory()->student()->create()->id, 'status' => 'submitted']);

    actingAs($mentor)->get(route('ai.index'))->assertOk()
        ->assertViewHas('entries', fn ($entries) => $entries->pluck('id')->all() === [$submission->id]);
});
