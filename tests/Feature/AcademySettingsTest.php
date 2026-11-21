<?php

use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

it('saves academy identity for administrators and uses it in the interface and new certificates', function () {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $admin = User::factory()->academyAdmin()->create();
    $course->enrollments()->create(['student_id' => $student->id]);
    $module = CourseModule::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Finished', 'status' => 'published']);
    LessonProgress::query()->create(['lesson_id' => $lesson->id, 'student_id' => $student->id, 'status' => 'completed']);
    actingAs($admin)->put(route('admin.settings.update'), ['name' => 'Yuridik Akademiya', 'certificate_issuer' => 'Huquq ta’lim markazi', 'support_email' => 'support@example.test'])->assertRedirect();
    actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Yuridik Akademiya')->assertSee('support@example.test');
    actingAs($admin)->post(route('admin.certificates.store', $course), ['student_id' => $student->id])->assertRedirect();
    assertDatabaseHas('certificates', ['issuer' => 'Huquq ta’lim markazi']);
    actingAs($admin)->put(route('admin.settings.update'), ['name' => 'New academy', 'certificate_issuer' => 'New issuer'])->assertRedirect();
    assertDatabaseHas('certificates', ['issuer' => 'Huquq ta’lim markazi']);
});

it('refuses academy settings access and updates from teaching and student roles', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
    actingAs($user)->put(route('admin.settings.update'), ['name' => 'Invalid', 'certificate_issuer' => 'Invalid'])->assertForbidden();
    expect(AcademySetting::query()->count())->toBe(0);
})->with(['teacher', 'mentor', 'student']);
