<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;

it('reminds only active students with unfinished work once per deadline', function () {
    $this->freezeTime();
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();
    $suspended = User::factory()->student()->create();
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $student->id]);
    CourseEnrollment::query()->create(['course_id' => $course->id, 'student_id' => $suspended->id, 'status' => 'suspended']);
    $assignment = $course->assignments()->create(['teacher_id' => $course->teacher_id, 'title' => 'Deadline testi', 'deadline' => now()->addHours(12)]);
    $before = $student->notifications()->count();
    $otherBefore = $suspended->notifications()->count();

    $this->artisan('academy:remind-deadlines')->assertSuccessful();
    $this->artisan('academy:remind-deadlines')->assertSuccessful();

    expect($student->notifications()->count())->toBe($before + 1);
    expect($suspended->notifications()->count())->toBe($otherBefore);
    $assignment->submissions()->create(['student_id' => $student->id, 'text' => 'Yuborilgan', 'status' => 'submitted']);
    $assignment->update(['deadline' => now()->addHours(18)]);
    $this->artisan('academy:remind-deadlines')->assertSuccessful();
    expect($student->notifications()->count())->toBe($before + 1);
});
