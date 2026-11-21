<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\EducationGroup;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View
    {
        return view('admin.groups', ['groups' => EducationGroup::query()->with('course.enrollments.student', 'members')->latest()->paginate(20), 'courses' => Course::query()->orderBy('title')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        EducationGroup::query()->create($request->validate(['name' => ['required', 'string', 'max:255'], 'course_id' => ['required', 'exists:courses,id']]));

        return back()->with('status', __('Guruh yaratildi.'));
    }

    public function update(Request $request, EducationGroup $group): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $group->update($data);

        return back()->with('status', __('Guruh yangilandi.'));
    }

    public function member(Request $request, EducationGroup $group): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')]]);
        abort_unless($group->course, 422);
        abort_unless($group->course->enrollments()->where('student_id', $data['student_id'])->where('status', 'active')->exists(), 422);
        $group->members()->syncWithoutDetaching([$data['student_id']]);

        return back()->with('status', __('Talaba guruhga qo‘shildi.'));
    }

    public function removeMember(EducationGroup $group, User $student): RedirectResponse
    {
        $group->members()->detach($student->id);

        return back()->with('status', __('Talaba guruhdan chiqarildi.'));
    }

    public function destroy(EducationGroup $group): RedirectResponse
    {
        abort_if(ClassSchedule::query()->where('group_id', $group->id)->exists(), 409, __('Jadvali bor guruhni o‘chirib bo‘lmaydi.'));
        abort_if(SimulationSession::query()->where('education_group_id', $group->id)->exists(), 409, __('Sud mashg‘uloti mavjud guruhni o‘chirib bo‘lmaydi.'));
        $group->delete();

        return back()->with('status', __('Guruh o‘chirildi.'));
    }
}
