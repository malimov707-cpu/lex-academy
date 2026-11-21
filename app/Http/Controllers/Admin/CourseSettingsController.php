<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\EducationCategory;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseSettingsController extends Controller
{
    public function edit(Request $request, Course $course): View
    {
        abort_unless($request->user()->isAdmin() || $course->isManagedBy($request->user()), 403);

        return view('admin.courses.settings', ['course' => $course->load('teacherAssignments.teacher'),
            'categories' => EducationCategory::query()->orderBy('name')->get(), 'teachers' => User::query()->where('role', 'teacher')->orderBy('name')->get()]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $course->isManagedBy($request->user()), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('courses')->ignore($course)],
            'description' => ['nullable', 'string', 'max:20000'], 'category_id' => ['required', 'exists:education_categories,id'],
            'education_level' => ['nullable', 'string', 'max:50'], 'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'language' => ['required', 'in:uz-latin,uz-cyr,ru'], 'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:520'],
            'learning_objectives' => ['nullable', 'string', 'max:10000'], 'prerequisites' => ['nullable', 'string', 'max:10000'],
            'published_at' => ['nullable', 'date'], 'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        foreach (['learning_objectives', 'prerequisites'] as $field) {
            $data[$field] = array_values(array_filter(array_map('trim', preg_split('/\R/u', $data[$field] ?? ''))));
        }
        unset($data['cover']);
        if ($request->hasFile('cover')) {
            $data['cover_image'] = $request->file('cover')->store('course-covers', 'local');
        }
        $course->update($data);

        return back()->with('status', __('Kurs yangilandi.'));
    }

    public function assignTeacher(Request $request, Course $course): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['teacher_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')]]);
        TeacherAssignment::query()->firstOrCreate($data + ['course_id' => $course->id]);

        return back()->with('status', __('O‘qituvchi biriktirildi.'));
    }

    public function removeTeacher(Request $request, Course $course, User $teacher): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        TeacherAssignment::query()->where('course_id', $course->id)->where('teacher_id', $teacher->id)->delete();

        return back()->with('status', __('Biriktirish o‘chirildi.'));
    }
}
