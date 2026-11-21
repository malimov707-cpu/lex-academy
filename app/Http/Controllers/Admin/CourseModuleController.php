<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseModuleController extends Controller
{
    public function index(Course $course): View
    {
        return view('admin.courses.modules', [
            'course' => $course->load('modules.lessons'),
        ]);
    }

    public function reorder(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer', 'distinct']]);
        DB::transaction(function () use ($data, $course): void {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            $ids = array_map('intval', $data['ids']);
            abort_unless($course->modules()->pluck('id')->sort()->values()->all() === collect($ids)->sort()->values()->all(), 422);
            foreach ($ids as $position => $id) {
                $course->modules()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(['saved' => true]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $course->modules()->create($data + [
            'sort_order' => $course->modules()->count() + 1,
        ]);

        return back()->with('status', __('Modul qo\'shildi.'));
    }

    public function update(Request $request, CourseModule $module): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $module->update($data);

        return back()->with('status', __('Modul yangilandi.'));
    }

    public function moveUp(CourseModule $module): RedirectResponse
    {
        $previous = CourseModule::query()
            ->where('course_id', $module->course_id)
            ->where('sort_order', '<', $module->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($previous) {
            [$module->sort_order, $previous->sort_order] = [$previous->sort_order, $module->sort_order];
            $module->save();
            $previous->save();
        }

        return back();
    }

    public function moveDown(CourseModule $module): RedirectResponse
    {
        $next = CourseModule::query()
            ->where('course_id', $module->course_id)
            ->where('sort_order', '>', $module->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            [$module->sort_order, $next->sort_order] = [$next->sort_order, $module->sort_order];
            $module->save();
            $next->save();
        }

        return back();
    }

    public function destroy(CourseModule $module): RedirectResponse
    {
        $module->delete();

        return back()->with('status', __('Modul o\'chirildi.'));
    }
}
