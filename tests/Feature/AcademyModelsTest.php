<?php

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\EducationCategory;
use App\Models\Lesson;
use App\Models\User;

it('creates published course with module and lesson via seeder relationships', function () {
    $teacher = User::factory()->create(['role' => UserRole::Teacher]);
    $category = EducationCategory::query()->create(['name' => 'Civil Law', 'slug' => 'civil-law']);

    $course = Course::query()->create([
        'category_id' => $category->id,
        'teacher_id' => $teacher->id,
        'title' => 'Civil Law Basics',
        'slug' => 'civil-law-basics',
        'status' => 'published',
    ]);

    $module = CourseModule::query()->create(['course_id' => $course->id, 'title' => 'Intro', 'sort_order' => 1]);
    $lesson = Lesson::query()->create(['module_id' => $module->id, 'title' => 'Lesson 1', 'status' => 'published', 'sort_order' => 1]);

    expect($lesson->module->course->id)->toBe($course->id);
});

it('enrolls a student uniquely', function () {
    $teacher = User::factory()->create(['role' => UserRole::Teacher]);
    $student = User::factory()->create(['role' => UserRole::Student]);
    $category = EducationCategory::query()->create(['name' => 'X', 'slug' => 'x']);
    $course = Course::query()->create([
        'category_id' => $category->id, 'teacher_id' => $teacher->id,
        'title' => 'T', 'slug' => 't',
    ]);

    $course->enrollments()->create(['student_id' => $student->id]);

    expect(fn () => $course->enrollments()->create(['student_id' => $student->id]))
        ->toThrow(Exception::class);
});
