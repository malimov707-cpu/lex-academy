<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\EducationAccess;
use App\Services\LearningAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningAnalyticsController extends Controller
{
    public function show(Request $request, Course $course, LearningAnalytics $analytics, EducationAccess $access): View
    {
        $access->learn($request->user(), $course);

        return view('student.analytics', ['course' => $course, 'student' => $request->user(), 'analytics' => $analytics->forCourse($course, $request->user())]);
    }
}
