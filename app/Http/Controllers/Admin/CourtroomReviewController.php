<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherReviewRequest;
use App\Models\EvaluationResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourtroomReviewController extends Controller
{
    public function show(EvaluationResult $evaluation): View
    {
        Gate::authorize('view', $evaluation);
        $evaluation->load([
            'session.student', 'session.scenario', 'session.version.roles',
            'session.actions.evidence', 'session.events', 'criterionResults.criterion', 'reviews.reviewer',
        ]);

        return view('admin.courtroom.review', ['evaluation' => $evaluation, 'session' => $evaluation->session]);
    }

    public function store(StoreTeacherReviewRequest $request, EvaluationResult $evaluation): RedirectResponse
    {
        Gate::authorize('update', $evaluation);
        DB::transaction(function () use ($request, $evaluation): void {
            $evaluation = EvaluationResult::query()->lockForUpdate()->findOrFail($evaluation->id);
            $data = $request->safe()->only(['decision', 'adjusted_score', 'comment', 'criterion_overrides']);
            if (($data['adjusted_score'] ?? null) === null && ! empty($data['criterion_overrides'])) {
                $data['adjusted_score'] = $evaluation->criterionResults()->get()->sum(
                    fn ($result): float => (float) ($data['criterion_overrides'][$result->evaluation_criterion_id] ?? $result->score),
                );
            }
            $evaluation->reviews()->create([
                'reviewer_id' => $request->user()->id,
                'has_explicit_criterion_scores' => ! empty($data['criterion_overrides']),
                ...$data,
            ]);
            $evaluation->update([
                'status' => $request->string('decision')->toString(),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('status', __('O‘qituvchi tekshiruvi saqlandi. Dastlabki avtomatik baho o‘zgartirilmadi.'));
    }
}
