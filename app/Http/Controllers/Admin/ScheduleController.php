<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\EducationGroup;
use App\Models\MentorAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'from' => ['nullable', 'date'], 'until' => ['nullable', 'date', 'after_or_equal:from']]);
        $courses = Course::query()->when(! $user->isAdmin(), function ($query) use ($user): void {
            if ($user->role->value === 'teacher') {
                $query->managedBy($user);
            } elseif ($user->role->value === 'mentor') {
                $query->whereIn('id', $user->mentorAssignments()->select('course_id'));
            } else {
                $query->whereHas('enrollments', fn ($query) => $query->where('student_id', $user->id)->where('status', 'active'));
            }
        })->get();
        $query = ClassSchedule::query()->whereIn('course_id', $courses->pluck('id'))->when($user->role->value === 'mentor', fn ($query) => $query->where('teacher_id', $user->id))
            ->when($user->role->value === 'student', fn ($query) => $query->where(fn ($query) => $query->whereNull('group_id')->orWhereHas('group.members', fn ($query) => $query->where('users.id', $user->id))))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', '>=', $date))
            ->when($filters['until'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', '<=', $date))
            ->with('course', 'teacher', 'group')->with(['attendance' => fn ($query) => $query->where('student_id', $user->id)])->orderBy('starts_at');
        $month = Carbon::parse(($filters['month'] ?? now()->format('Y-m')).'-01')->startOfMonth();
        $calendar = (clone $query)->whereBetween('starts_at', [$month, $month->copy()->endOfMonth()])->get()->groupBy(fn (ClassSchedule $schedule) => $schedule->starts_at->format('Y-m-d'));
        $schedules = (clone $query)->paginate(30)->withQueryString();

        return view('schedule.index', ['schedules' => $schedules, 'month' => $month, 'calendar' => $calendar, 'courses' => $courses,
            'hosts' => User::query()->whereIn('role', ['teacher', 'mentor'])->when(! $user->isAdmin(), fn ($query) => $query->where(fn ($query) => $query->whereKey($user->id)->orWhereIn('id', MentorAssignment::query()->whereIn('course_id', $courses->pluck('id'))->select('mentor_id'))))->orderBy('name')->get(),
            'groups' => EducationGroup::query()->whereIn('course_id', $courses->pluck('id'))->get(), 'canManage' => $user->isAdmin() || $user->role->value === 'teacher']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host_id' => ['nullable', 'exists:users,id'],
            'course_id' => ['required', 'exists:courses,id'], 'group_id' => ['nullable', 'exists:education_groups,id'],
            'title' => ['required', 'string', 'max:255'], 'kind' => ['required', 'in:lesson,mentor_meeting'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'location' => ['nullable', 'string', 'max:255'],
        ]);
        $course = Course::findOrFail($data['course_id']);
        $this->authorizeCourse($request, $course);
        if (! empty($data['group_id'])) {
            abort_unless(EducationGroup::query()->whereKey($data['group_id'])->where('course_id', $course->id)->exists(), 422);
        }
        $hostId = (int) ($data['host_id'] ?? $course->teacher_id);
        if ($hostId !== $course->teacher_id) {
            abort_unless($data['kind'] === 'mentor_meeting' && MentorAssignment::query()->where('course_id', $course->id)->where('mentor_id', $hostId)->exists(), 422);
        }
        unset($data['host_id']);
        $data['teacher_id'] = $hostId;
        ClassSchedule::query()->create($data);

        return back()->with('status', __('Jadvalga qo‘shildi.'));
    }

    public function update(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        $this->authorizeCourse($request, $schedule->course);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'location' => ['nullable', 'string', 'max:255']]);
        $schedule->update($data);

        return back()->with('status', __('Jadval yangilandi.'));
    }

    public function show(Request $request, ClassSchedule $schedule): View
    {
        $this->authorizeCourse($request, $schedule->course);
        $students = $schedule->course->enrollments()->where('status', 'active')->with('student')
            ->when($schedule->group_id, fn ($query) => $query->whereIn('student_id', $schedule->group->members()->select('users.id')))->get();
        $records = $schedule->attendance()->get()->keyBy('student_id');

        return view('schedule.attendance', compact('schedule', 'students', 'records'));
    }

    public function attendance(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        $this->authorizeCourse($request, $schedule->course);
        $data = $request->validate(['records' => ['required', 'array', 'max:500'], 'records.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])], 'records.*.reason' => ['nullable', 'string', 'max:1000']]);
        $ids = $schedule->course->enrollments()->where('status', 'active')
            ->when($schedule->group_id, fn ($query) => $query->whereIn('student_id', $schedule->group->members()->select('users.id')))->pluck('student_id')->all();
        DB::transaction(function () use ($data, $ids, $schedule, $request): void {
            foreach ($data['records'] as $id => $record) {
                if (! in_array((int) $id, $ids, true)) {
                    throw ValidationException::withMessages(['records' => __('Talaba bu darsga biriktirilmagan.')]);
                }
                AttendanceRecord::query()->updateOrCreate(['class_schedule_id' => $schedule->id, 'student_id' => $id], $record + ['recorded_by' => $request->user()->id]);
            }
        });

        return back()->with('status', __('Davomat saqlandi.'));
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->role->value === 'teacher' && $course->isManagedBy($request->user())), 403);
    }
}
