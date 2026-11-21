<?php

namespace App\Services\VirtualCourtroom;

use App\Models\EvaluationCriterion;
use App\Models\ScenarioVersion;
use App\Models\SimulationSession;
use App\Models\TeacherReview;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourtroomPilotReport
{
    /**
     * @return array{
     *     completed_students: int, repeat_students: int,
     *     criteria: array<string, array{name: string, max_score: float, count: int, average_delta: ?float}>,
     *     pairs: list<array>, pending_pairs: list<array>
     * }
     */
    public function forRole(ScenarioVersion $version, string $role): array
    {
        $version->loadMissing('rubric.criteria');
        $criteria = $version->rubric?->criteria->whereIn('key', ['legal_reasoning', 'evidence_analysis']) ?? collect();
        $summary = $criteria->mapWithKeys(fn (EvaluationCriterion $criterion): array => [
            $criterion->key => ['name' => $criterion->name, 'max_score' => (float) $criterion->max_score, 'count' => 0, 'average_delta' => null],
        ])->all();

        $byStudent = SimulationSession::query()
            ->where('scenario_id', $version->scenario_id)
            ->where('scenario_version_id', $version->id)
            ->where('selected_role_key', $role)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->with([
                'student:id,name',
                'evaluation:id,simulation_session_id',
                'evaluation.reviews' => fn (HasMany $reviews): HasMany => $reviews
                    ->select('id', 'evaluation_result_id', 'criterion_overrides', 'has_explicit_criterion_scores')
                    ->orderByDesc('id')->limit(1),
            ])
            ->orderBy('student_id')->orderBy('completed_at')->orderBy('id')
            ->get(['id', 'student_id', 'settings', 'completed_at'])
            ->groupBy('student_id');

        $pairs = [];
        $pendingPairs = [];
        $repeatStudents = 0;
        foreach ($byStudent as $attempts) {
            if ($attempts->count() < 2) {
                continue;
            }
            $repeatStudents++;
            $graded = $attempts->filter(fn (SimulationSession $attempt): bool => $attempt->evaluation !== null);
            $last = $graded->last();
            if (! $last) {
                continue;
            }
            $method = $last->settings['scoring_version'] ?? null;
            $first = $graded->first(fn (SimulationSession $attempt): bool => ($attempt->settings['scoring_version'] ?? null) === $method);
            if ($first->is($last)) {
                continue;
            }

            $firstMarks = $this->explicitMarks($first->evaluation->reviews->first());
            $lastMarks = $this->explicitMarks($last->evaluation->reviews->first());
            $scores = [];
            $missingCriteria = [];
            foreach ($criteria as $criterion) {
                if (! isset($firstMarks[$criterion->id], $lastMarks[$criterion->id])) {
                    $missingCriteria[] = $criterion->key;

                    continue;
                }
                $before = (float) $firstMarks[$criterion->id];
                $after = (float) $lastMarks[$criterion->id];
                $scores[$criterion->key] = ['first' => $before, 'last' => $after, 'delta' => round($after - $before, 2)];
            }

            $pair = [
                'student_name' => $last->student->name,
                'first_evaluation_id' => $first->evaluation->id, 'last_evaluation_id' => $last->evaluation->id,
                'first_completed_at' => $first->completed_at, 'last_completed_at' => $last->completed_at,
                'scores' => $scores, 'missing_criteria' => $missingCriteria,
            ];
            if ($scores !== []) {
                $pairs[] = $pair;
            }
            if ($missingCriteria !== []) {
                $pendingPairs[] = $pair;
            }
        }

        foreach ($criteria as $criterion) {
            $deltas = collect($pairs)->pluck('scores.'.$criterion->key.'.delta')->filter(fn (?float $delta): bool => $delta !== null);
            $summary[$criterion->key]['count'] = $deltas->count();
            $summary[$criterion->key]['average_delta'] = $deltas->isEmpty() ? null : round($deltas->avg(), 2);
        }

        return [
            'completed_students' => $byStudent->count(), 'repeat_students' => $repeatStudents,
            'criteria' => $summary, 'pairs' => $pairs, 'pending_pairs' => $pendingPairs,
        ];
    }

    /** @return array<int, float|int|string> */
    private function explicitMarks(?TeacherReview $review): array
    {
        return $review?->has_explicit_criterion_scores ? ($review->criterion_overrides ?? []) : [];
    }
}
