<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherStudentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate(['student' => ['nullable', 'integer', 'min:1']]);
        $students = User::query()->where('role', UserRole::Student)
            ->whereHas('enrollments', fn ($query) => $query->forTeacher($request->user()))
            ->with(['enrollments' => fn ($query) => $query->forTeacher($request->user())->with('course:id,slug,title')])
            ->when($filters['student'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')->orderBy('id')->paginate(24, ['id', 'name', 'email'])->withQueryString();

        return view('teacher.students', compact('students'));
    }
}
