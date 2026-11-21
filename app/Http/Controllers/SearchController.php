<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LibraryResource;
use App\Models\SimulationScenario;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Global qidiruv — rol doirasida, faqat o'zi ko'ra oladigan yozuvlar.
     *
     * @return array<int, array{label: string, items: Collection<int, array{title: string, meta: string, url: string, icon: string}>}>
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['q'] ?? '');
        $results = [];

        if (mb_strlen($term) >= 2) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

            $results = array_values(array_filter([
                $this->courses($user, $like),
                $this->lessons($user, $like),
                $this->people($user, $like),
                $this->teacherScenarios($user, $like),
                $this->library($like),
            ]));
        }

        return view('search.index', [
            'term' => $term,
            'results' => $results,
            'total' => collect($results)->sum(fn (array $group): int => $group['items']->count()),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function teacherScenarios(User $user, string $like): ?array
    {
        if (! $user->hasRole(UserRole::Teacher)) {
            return null;
        }

        $items = SimulationScenario::query()->where('created_by', $user->id)
            ->whereRaw("title LIKE ? ESCAPE '!'", [$like])
            ->orderBy('title')->limit(8)->get(['id', 'slug', 'title'])
            ->map(fn (SimulationScenario $scenario): array => [
                'title' => $scenario->title, 'meta' => __('Virtual sud'),
                'url' => route('admin.courtroom.preview', $scenario), 'icon' => 'courtroom',
            ]);

        return $items->isEmpty() ? null : ['label' => __('Simulyatsiyalar'), 'items' => $items];
    }

    /** @return array<string, mixed>|null */
    private function library(string $like): ?array
    {
        $items = LibraryResource::query()->where('status', 'published')
            ->where(fn ($query) => $query->whereRaw("title LIKE ? ESCAPE '!'", [$like])->orWhereRaw("topic LIKE ? ESCAPE '!'", [$like]))
            ->orderBy('title')->limit(8)->get(['id', 'title', 'topic'])->map(fn (LibraryResource $resource): array => [
                'title' => $resource->title, 'meta' => __('Huquqiy kutubxona').' · '.$resource->topic,
                'url' => route('library.show', $resource), 'icon' => 'sources',
            ]);

        return $items->isEmpty() ? null : ['label' => __('Huquqiy kutubxona'), 'items' => $items];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function courses(User $user, string $like): ?array
    {
        $query = Course::query()->select('id', 'title', 'slug', 'status', 'teacher_id')
            ->whereRaw("title LIKE ? ESCAPE '!'", [$like])->limit(8);

        if ($user->hasRole(UserRole::Student)) {
            $query->where('status', 'published')
                ->whereIn('id', $user->enrollments()->where('status', 'active')->select('course_id'));
        } elseif ($user->hasRole(UserRole::Teacher)) {
            $query->managedBy($user);
        } elseif ($user->hasRole(UserRole::Mentor)) {
            $query->where('status', 'published')->whereIn('id', $user->mentorAssignments()->select('course_id'));
        }

        $items = $query->get()->map(function (Course $course) use ($user): array {
            $url = match ($user->role) {
                UserRole::Student => route('student.course', $course),
                UserRole::Mentor => route('public.courses.show', $course->slug),
                default => route('admin.courses.modules.index', $course),
            };

            return [
                'title' => $course->title,
                'meta' => __('Kurs').' · '.$course->status,
                'url' => $url,
                'icon' => 'courses',
            ];
        });

        return $items->isEmpty() ? null : ['label' => __('Kurslar'), 'items' => $items];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lessons(User $user, string $like): ?array
    {
        if ($user->hasRole(UserRole::Mentor)) {
            return null;
        }
        $query = Lesson::query()->select('id', 'title', 'module_id', 'status')
            ->whereRaw("title LIKE ? ESCAPE '!'", [$like])
            ->where('status', 'published')
            ->with('module.course')
            ->limit(10);
        if ($user->hasRole(UserRole::Student)) {
            $query->whereHas('module.course', fn ($query) => $query->where('status', 'published')
                ->whereHas('enrollments', fn ($query) => $query->where('student_id', $user->id)->where('status', 'active')));
        } elseif ($user->hasRole(UserRole::Teacher)) {
            $query->whereHas('module.course', fn ($query) => $query->managedBy($user));
        }
        $items = $query->get()->map(function (Lesson $lesson) use ($user): array {
            return [
                'title' => $lesson->title,
                'meta' => __('Dars'),
                'url' => $user->hasRole(UserRole::Student) ? route('student.lesson', [$lesson->module->course, $lesson]) : route('admin.lessons.edit', $lesson),
                'icon' => 'lesson',
            ];
        });

        return $items->isEmpty() ? null : ['label' => __('Darslar'), 'items' => $items];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function people(User $user, string $like): ?array
    {
        if ($user->hasRole(UserRole::Student)) {
            return null;
        }

        if ($user->hasRole(UserRole::Mentor)) {
            $items = $user->mentorAssignments()->whereHas('student', fn ($query) => $query
                ->where(fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("email LIKE ? ESCAPE '!'", [$like])))
                ->with('student', 'course')->limit(10)->get()->map(fn ($assignment): array => [
                    'title' => $assignment->student->name,
                    'meta' => $assignment->student->email.' · '.$assignment->course->title,
                    'url' => route('mentor.students.show', $assignment), 'icon' => 'users',
                ]);

            return $items->isEmpty() ? null : ['label' => __('Odamlar'), 'items' => $items];
        }

        $query = User::query()->select('id', 'name', 'email', 'role')
            ->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]))
            ->limit(10);

        if ($user->hasRole(UserRole::Teacher)) {
            $items = $query->where('role', UserRole::Student)
                ->whereHas('enrollments', fn ($query) => $query->forTeacher($user))
                ->orderBy('name')->get()->map(fn (User $student): array => [
                    'title' => $student->name, 'meta' => $student->email,
                    'url' => route('teacher.students.index', ['student' => $student->id]), 'icon' => 'users',
                ]);

            return $items->isEmpty() ? null : ['label' => __('Talabalar'), 'items' => $items];
        }

        if (! $user->hasRole(UserRole::SuperAdmin)) {
            $query->whereNotIn('role', [UserRole::SuperAdmin, UserRole::AcademyAdmin]);
        }

        $items = $query->get()->map(function (User $person): array {
            $url = route('admin.users.index', ['search' => $person->email]);

            return [
                'title' => $person->name,
                'meta' => $person->email.' · '.__($person->role->value),
                'url' => $url,
                'icon' => 'users',
            ];
        });

        return $items->isEmpty() ? null : ['label' => __('Odamlar'), 'items' => $items];
    }
}
