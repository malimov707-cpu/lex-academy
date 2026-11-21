<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LegalSource;
use App\Models\LegalSourceCheck;
use App\Models\LibraryResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LegalSourceController extends Controller
{
    public function index(Request $request): View
    {
        $courses = Course::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->managedBy($request->user()))->get();

        return view('admin.legal-sources', [
            'courses' => $courses,
            'topics' => LibraryResource::query()->where('status', 'published')->distinct()->orderBy('topic')->pluck('topic'),
            'sources' => LegalSource::query()
                ->when(! $request->user()->isAdmin(), fn ($query) => $query->whereIn('course_id', $courses->pluck('id')))
                ->with('course')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['course_id' => ['nullable', 'exists:courses,id'], 'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:https', 'max:2000'], 'excerpt' => ['required', 'string', 'max:20000'],
            'topic' => ['nullable', 'string', 'max:100', Rule::in(LibraryResource::query()->where('status', 'published')->distinct()->pluck('topic')->all())]]);
        if (! $request->user()->isAdmin()) {
            $course = Course::find($data['course_id'] ?? 0);
            abort_unless($course && $course->isManagedBy($request->user()), 403);
        }
        LegalSource::query()->create($data + ['created_by' => $request->user()->id]);

        return back()->with('status', __('Manba qoralamasi saqlandi.'));
    }

    public function update(Request $request, LegalSource $source): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'status' => ['required', 'in:verified,archived'],
            'confirmed' => ['accepted'],
            'topic' => ['nullable', 'string', 'max:100', Rule::in(LibraryResource::query()->where('status', 'published')->distinct()->pluck('topic')->all())],
        ]);
        $source->update(['status' => $data['status'], 'topic' => $data['topic'] ?? null, 'verified_by' => $request->user()->id, 'verified_at' => now()]);

        return back()->with('status', __('Manba holati yangilandi.'));
    }

    public function reviewCheck(Request $request, LegalSource $source, LegalSourceCheck $check): RedirectResponse
    {
        $course = $source->course;
        abort_unless($request->user()->isAdmin() || ($course && $course->isManagedBy($request->user())), 403);
        abort_unless($check->legal_source_id === $source->id && $check->status === 'changed', 404);

        $data = $request->validate([
            'impact_status' => ['required', 'in:no_known_impact,review_required,verified_outdated,updated,confirmed_current'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $check->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'impact_status' => $data['impact_status'],
            'review_note' => $data['review_note'] ?? null,
        ]);

        return back()->with('status', __('Sahifa o‘zgarishi va o‘quv materiali ta’siri qayd etildi. Bu huquqiy kuchni avtomatik tasdiqlamaydi.'));
    }
}
