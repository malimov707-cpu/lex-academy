<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\TeacherDashboardOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDashboardController extends Controller
{
    public function index(Request $request, TeacherDashboardOverview $overview): View
    {
        return view('teacher.dashboard', $overview->forTeacher($request->user()));
    }
}
