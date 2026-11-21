<?php

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\EducationCategory;
use App\Models\Lesson;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('admin creates and publishes a course', function () {
    $admin = User::factory()->academyAdmin()->create();
    $teacher = User::factory()->teacher()->create();
    $category = EducationCategory::factory()->create();

    actingAs($admin)->post('/admin/courses', [
        'title' => 'Jinoyat huquqi', 'category_id' => $category->id,
        'teacher_id' => $teacher->id, 'difficulty' => 'beginner',
        'language' => 'uz-latin', 'status' => 'draft',
    ])->assertRedirect();

    $course = Course::query()->where('title', 'Jinoyat huquqi')->firstOrFail();
    actingAs($admin)->patch("/admin/courses/{$course->id}/publish")->assertRedirect();

    expect($course->fresh()->status)->toBe('published')
        ->and($course->fresh()->published_at)->not->toBeNull();
});

it('teacher creates module and lesson then publishes lesson', function () {
    $course = Course::factory()->create();
    actingAs($course->teacher)->post("/admin/courses/{$course->id}/modules", ['title' => 'Modul 1'])->assertRedirect();

    $module = CourseModule::query()->where('course_id', $course->id)->firstOrFail();
    actingAs($course->teacher)->post("/admin/modules/{$module->id}/lessons", ['title' => 'Dars 1'])->assertRedirect();

    $lesson = Lesson::query()->where('module_id', $module->id)->firstOrFail();
    expect($lesson->status)->toBe('draft');

    actingAs($course->teacher)->patch("/admin/lessons/{$lesson->id}/publish")->assertRedirect();
    expect($lesson->fresh()->status)->toBe('published');
});

it('sorts modules up and down', function () {
    $course = Course::factory()->create();
    $a = $course->modules()->create(['title' => 'A', 'sort_order' => 1]);
    $b = $course->modules()->create(['title' => 'B', 'sort_order' => 2]);

    actingAs($course->teacher)->patch("/admin/modules/{$b->id}/up")->assertRedirect();

    expect($b->fresh()->sort_order)->toBe(1)->and($a->fresh()->sort_order)->toBe(2);
});

it('deleting category cascades courses', function () {
    $course = Course::factory()->create();

    actingAs(User::factory()->superAdmin()->create())
        ->delete('/admin/categories/'.$course->category_id)->assertRedirect();

    expect(Course::query()->find($course->id))->toBeNull();
});

it('forbids unassigned roles from course admin', function () {
    $mentor = User::factory()->mentor()->create();

    actingAs($mentor)->get('/admin/courses')->assertForbidden();
});
