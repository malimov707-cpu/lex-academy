<?php

namespace App\Http\Controllers\Public;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Public landing page — platformani tanishtiruvchi bosh sahifa.
     */
    public function home(): View
    {
        $courses = Course::query()
            ->where('status', 'published')
            ->with('teacher')
            ->latest()
            ->take(6)
            ->get();

        $teachers = User::query()
            ->whereIn('role', [UserRole::Teacher, UserRole::Mentor])
            ->orderBy('name')
            ->take(8)
            ->get();

        return view('public.home', [
            'courses' => $courses,
            'teachers' => $teachers,
        ]);
    }

    /**
     * Kurslar katalogi — faqat nashr etilgan kurslar.
     */
    public function courses(): View
    {
        $courses = Course::query()
            ->where('status', 'published')
            ->with('teacher')
            ->latest()
            ->paginate(12);

        return view('public.courses', ['courses' => $courses]);
    }

    /**
     * Kurs haqida batafsil ma'lumot.
     */
    public function courseShow(string $slug): View
    {
        $course = Course::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->with('teacher')
            ->firstOrFail();

        return view('public.course-show', ['course' => $course]);
    }

    /**
     * Platforma haqida.
     */
    public function about(): View
    {
        return view('public.about');
    }

    /**
     * Aloqa.
     */
    public function contact(): View
    {
        return view('public.contact');
    }

    /**
     * Savol-javoblar.
     */
    public function faq(): View
    {
        return view('public.faq');
    }
}
