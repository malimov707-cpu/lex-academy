<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\EducationGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $courses = Course::query()->when(! $user->isAdmin(), function ($query) use ($user): void {
            if ($user->role->value === 'teacher') {
                $query->managedBy($user);
            } else {
                $query->whereHas('enrollments', fn ($query) => $query->where('student_id', $user->id)->where('status', 'active'));
            }
        })->get();
        $announcements = DB::table('announcements')->where(function ($query) use ($courses): void {
            $query->whereNull('course_id')->orWhereIn('course_id', $courses->pluck('id'));
        })->when(! $user->isAdmin() && $user->role->value !== 'teacher', fn ($query) => $query->where(fn ($query) => $query->whereNull('group_id')->orWhereIn('group_id', DB::table('group_members')->where('user_id', $user->id)->select('group_id'))))
            ->orderByDesc('id')->paginate(20);

        return view('announcements', compact('announcements', 'courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:10000'], 'course_id' => ['nullable', 'exists:courses,id'], 'group_id' => ['nullable', 'exists:education_groups,id']]);
        $course = empty($data['course_id']) ? null : Course::findOrFail($data['course_id']);
        abort_unless($request->user()->isAdmin() || ($course && $course->isManagedBy($request->user())), 403);
        if (! empty($data['group_id'])) {
            abort_unless($course && EducationGroup::query()->whereKey($data['group_id'])->where('course_id', $course->id)->exists(), 422);
        }
        DB::table('announcements')->insert($data + ['user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('E’lon nashr qilindi.'));
    }
}
