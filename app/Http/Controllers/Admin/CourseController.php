<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\EducationCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.courses.index', [
            'courses' => Course::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->managedBy($request->user()))->with(['category', 'teacher'])->latest()->paginate(20),
            'categories' => EducationCategory::query()->orderBy('name')->get(),
            'teachers' => User::query()->where('role', UserRole::Teacher)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Course::query()->create($data + ['slug' => Str::slug($data['title']).'-'.Str::random(4)]);

        return back()->with('status', __('Kurs yaratildi.'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $this->validated($request);
        if (! $request->user()->isAdmin()) {
            unset($data['teacher_id']);
        }
        $course->update($data);

        return back()->with('status', __('Kurs yangilandi.'));
    }

    public function publish(Course $course): RedirectResponse
    {
        $course->update(['status' => 'published', 'published_at' => now()]);

        return back()->with('status', __('Kurs nashr qilindi.'));
    }

    public function archive(Course $course): RedirectResponse
    {
        $course->update(['status' => 'archived']);

        return back()->with('status', __('Kurs arxivlandi.'));
    }

    public function destroy(Course $course): RedirectResponse
    {
        abort_if($course->certificates()->exists(), 409, __('Sertifikati bor kursni arxivlang.'));
        $course->delete();

        return back()->with('status', __('Kurs o\'chirildi.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        if (! $request->user()->isAdmin()) {
            $request->merge(['teacher_id' => $request->user()->id]);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:education_categories,id'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'language' => ['required', 'in:uz-latin,uz-cyr,ru'],
            'education_level' => ['nullable', 'string', 'max:50'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'status' => ['required', 'in:draft,review,published,archived'],
        ]);
    }
}
