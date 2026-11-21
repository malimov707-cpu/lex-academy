<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Course $course): View
    {
        return view('admin.courses.enrollments', [
            'course' => $course->load('enrollments.student'),
            'students' => User::query()->where('role', UserRole::Student)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')],
            'status' => ['required', 'in:active,suspended,completed'],
        ]);

        CourseEnrollment::query()->updateOrCreate(
            ['course_id' => $course->id, 'student_id' => $data['student_id']],
            ['status' => $data['status']],
        );

        return back()->with('status', __('Talaba kursga biriktirildi.'));
    }

    public function destroy(CourseEnrollment $enrollment): RedirectResponse
    {
        $enrollment->delete();

        return back()->with('status', __('Biriktirish o\'chirildi.'));
    }
}
