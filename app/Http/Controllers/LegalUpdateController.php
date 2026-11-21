<?php

namespace App\Http\Controllers;

use App\Models\LegalSource;
use App\Models\LegalSourceCheck;
use App\Models\LibraryResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LegalUpdateController extends Controller
{
    public function index(Request $request): View
    {
        $sources = LegalSource::query()
            ->with([
                'course.modules' => fn ($query) => $query->with(['lessons:id,module_id,title'])->withCount('lessons'),
                'checks' => fn ($query) => $query->latest('checked_at')->limit(2),
            ])
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->whereNotNull('verified_by')
            ->latest('verified_at')
            ->paginate(12);

        $verifiedSources = LegalSource::query()->where('status', 'verified')
            ->whereNotNull('verified_at')->whereNotNull('verified_by')->count();
        $linkedCourses = LegalSource::query()->where('status', 'verified')
            ->whereNotNull('verified_at')->whereNotNull('verified_by')
            ->whereNotNull('course_id')->distinct()->count('course_id');
        $pendingReviewCount = LegalSourceCheck::query()->where('status', 'changed')->whereNull('reviewed_at')->count();
        $latestCheck = LegalSourceCheck::query()->latest('checked_at')->first(['checked_at'])?->checked_at;

        return view('legal-updates.index', [
            'sources' => $sources,
            'verifiedSources' => $verifiedSources,
            'linkedCourses' => $linkedCourses,
            'pendingReviewCount' => $pendingReviewCount,
            'latestCheck' => $latestCheck,
            'topics' => LibraryResource::query()->where('status', 'published')->distinct()->orderBy('topic')->pluck('topic'),
            'subscribedTopics' => $request->user()->legalTopicSubscriptions()->pluck('topic')->all(),
        ]);
    }

    public function updateSubscriptions(Request $request): RedirectResponse
    {
        $availableTopics = LibraryResource::query()->where('status', 'published')->distinct()->pluck('topic')->all();
        $data = $request->validate([
            'topics' => ['sometimes', 'array', 'max:50'],
            'topics.*' => ['required', 'string', 'distinct', Rule::in($availableTopics)],
        ]);

        $selectedTopics = $data['topics'] ?? [];
        DB::transaction(function () use ($request, $selectedTopics): void {
            $subscriptions = $request->user()->legalTopicSubscriptions();
            if ($selectedTopics === []) {
                $subscriptions->delete();

                return;
            }

            $subscriptions->whereNotIn('topic', $selectedTopics)->delete();
            foreach ($selectedTopics as $topic) {
                $subscriptions->firstOrCreate(['topic' => $topic]);
            }
        });

        return back()->with('status', __('Mavzu yangilanishlariga obuna saqlandi.'));
    }
}
