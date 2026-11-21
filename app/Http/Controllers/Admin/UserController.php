<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\MentorAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::enum(UserRole::class)]]);

        return view('admin.users', [
            'users' => User::query()->when(! $request->user()->hasRole(UserRole::SuperAdmin), fn ($query) => $query->whereNotIn('role', ['super_admin', 'academy_admin']))
                ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
                ->orderBy('name')->paginate(20)->withQueryString(),
            'roles' => $request->user()->hasRole(UserRole::SuperAdmin) ? UserRole::labels() : array_intersect_key(UserRole::labels(), array_flip(['teacher', 'mentor', 'student'])),
            'courses' => Course::query()->orderBy('title')->get(),
            'students' => User::query()->where('role', 'student')->orderBy('name')->get(),
            'mentors' => User::query()->where('role', 'mentor')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()], 'role' => ['required', Rule::enum(UserRole::class)]]);
        $this->authorizeRole($request, $data['role']);
        User::query()->create($data);

        return back()->with('status', __('Foydalanuvchi yaratildi.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeRole($request, $user->role->value);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)], 'password' => ['nullable', Password::min(12)->mixedCase()->numbers()]]);
        $this->authorizeRole($request, $data['role']);
        abort_if($user->id === $request->user()->id && $data['role'] !== $user->role->value, 422, __('O‘z rolingizni bu yerdan o‘zgartira olmaysiz.'));
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        return back()->with('status', __('Foydalanuvchi yangilandi.'));
    }

    public function assignMentor(Request $request): RedirectResponse
    {
        $data = $request->validate(['mentor_id' => ['required', Rule::exists('users', 'id')->where('role', 'mentor')],
            'student_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')], 'course_id' => ['required', 'exists:courses,id']]);
        abort_unless(Course::findOrFail($data['course_id'])->enrollments()->where('student_id', $data['student_id'])->where('status', 'active')->exists(), 422);
        MentorAssignment::query()->firstOrCreate($data);

        return back()->with('status', __('Mentor biriktirildi.'));
    }

    private function authorizeRole(Request $request, string $role): void
    {
        abort_unless($request->user()->hasRole(UserRole::SuperAdmin) || in_array($role, ['teacher', 'mentor', 'student'], true), 403);
    }
}
