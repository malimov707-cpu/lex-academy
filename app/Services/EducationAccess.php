<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;

class EducationAccess
{
    public function learn(User $user, Course $course, ?Lesson $lesson = null): void
    {
        if ($lesson) {
            abort_unless($lesson->module->course_id === $course->id, 404);
            abort_unless($lesson->status === 'published', 404);
        }
        abort_unless($course->status === 'published' && $user->hasRole(UserRole::Student)
            && $course->enrollments()->where('student_id', $user->id)->where('status', 'active')->exists(), 403);
    }
}
