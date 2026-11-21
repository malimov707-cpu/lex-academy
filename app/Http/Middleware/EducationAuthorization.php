<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Services\EducationAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EducationAuthorization
{
    public function __construct(private EducationAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        $course = $request->route('course');
        $module = $request->route('module');
        $lesson = $request->route('lesson');
        $name = $request->route()->getName() ?? '';
        if (str_starts_with($name, 'student.') && $course instanceof Course) {
            $quiz = $request->route('quiz');
            if ($quiz instanceof Quiz) {
                abort_unless($quiz->course_id === $course->id, 404);
            }
            $this->access->learn($user, $course, $lesson instanceof Lesson ? $lesson : null);
        }
        if (str_starts_with($name, 'admin.')) {
            if ($user->hasRole(UserRole::Teacher)) {
                abort_if(str_contains($name, 'categories') || str_contains($name, 'enrollments'), 403);
                $owner = $course instanceof Course ? $course : null;
                if ($module instanceof CourseModule) {
                    $owner = $module->course;
                }
                if ($lesson instanceof Lesson) {
                    $owner = $lesson->module->course;
                }
                if ($owner) {
                    abort_unless($owner->isManagedBy($user), 403);
                }
            }
        }

        return $next($request);
    }
}
