<?php

namespace App\Services\VirtualCourtroom;

use App\Models\EvaluationCriterion;
use App\Models\EvaluationResult;
use App\Models\ScenarioEvidence;
use App\Models\SessionAction;
use App\Models\SimulationSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SimulationEvaluator
{
    public const int SCORING_VERSION = 2;

    public function generate(SimulationSession $session): EvaluationResult
    {
        $session->loadMissing(['version.rubric.criteria', 'version.stages', 'actions', 'events']);
        if ($session->status !== 'completed') {
            throw new \LogicException('Baholash faqat yakunlangan simulyatsiya uchun yaratiladi.');
        }
        $rubric = $session->version->rubric;
        if (! $rubric) {
            throw new \LogicException('Ssenariy rubrikasi topilmadi.');
        }

        return DB::transaction(function () use ($session, $rubric): EvaluationResult {
            $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            $existing = EvaluationResult::query()->where('simulation_session_id', $session->id)->first();
            if ($existing) {
                return $existing->load(['criterionResults.criterion', 'reviews.reviewer']);
            }
            $accepted = $session->actions->where('validation_status', 'accepted')->sortBy('id')->values();
            $exhibits = $session->version->evidence()->where('status', 'active')->get()
                ->filter(fn (ScenarioEvidence $exhibit): bool => $exhibit->isVisibleTo($session->selected_role_key))
                ->keyBy('id');
            [$contributions, $citations] = $this->discussionEvidence($accepted, $exhibits);
            $results = $rubric->criteria->map(function (EvaluationCriterion $criterion) use ($session, $accepted, $contributions, $citations, $exhibits): array {
                [$ratio, $actionIds, $eventIds, $rationale] = $this->criterionEvidence($criterion->key, $session, $accepted, $contributions, $citations, $exhibits);

                return [
                    'criterion' => $criterion,
                    'ratio' => $ratio,
                    'score' => round($criterion->max_score * $ratio, 2),
                    'action_ids' => $actionIds,
                    'event_ids' => $eventIds,
                    'rationale' => $rationale,
                ];
            });
            $overall = round((float) $results->sum('score'), 2);
            $strengths = $results->where('ratio', '>=', 0.7)->pluck('criterion.name')->values()->all();
            $improvements = $results->where('ratio', '<', 0.7)->map(
                fn (array $result): string => $result['criterion']->name.': '.$result['rationale'],
            )->values()->all();

            $locked->update(['settings' => [...($locked->settings ?? []), 'scoring_version' => self::SCORING_VERSION]]);
            $evaluation = EvaluationResult::query()->updateOrCreate(
                ['simulation_session_id' => $session->id],
                [
                    'status' => 'draft',
                    'overall_score' => $overall,
                    'generated_by' => 'system',
                    'strengths' => $strengths,
                    'improvements' => $improvements,
                    'next_exercises' => ['O‘qituvchi izohi asosida past natijali mezon bo‘yicha qayta mashq qiling.'],
                    'generated_at' => now(),
                ],
            );
            $evaluation->criterionResults()->delete();
            foreach ($results as $result) {
                $evaluation->criterionResults()->create([
                    'evaluation_criterion_id' => $result['criterion']->id,
                    'score' => $result['score'],
                    'rationale' => $result['rationale'],
                    'evidence_action_ids' => $result['action_ids'],
                    'evidence_event_ids' => $result['event_ids'],
                ]);
            }

            return $evaluation->fresh(['criterionResults.criterion', 'reviews.reviewer']);
        });
    }

    /**
     * @param  Collection<int, SessionAction>  $actions
     * @param  Collection<int, ScenarioEvidence>  $exhibits
     * @return array{Collection<int, SessionAction>, Collection<int, SessionAction>}
     */
    private function discussionEvidence(Collection $actions, Collection $exhibits): array
    {
        $contributions = [];
        $citations = [];
        // Mark exact exhibit titles so punctuation in a title cannot split a citation.
        $citationEndings = $exhibits->mapWithKeys(fn (ScenarioEvidence $exhibit): array => ['” ('.$exhibit->title.')' => "”\0"])->all();
        foreach ($actions->where('action_type', '!=', 'advance_stage') as $action) {
            $attributedText = strtr((string) $action->content, $citationEndings);
            $content = preg_replace('/“[^\x00]*”\x00/u', ' ', $attributedText);
            $exhibit = $exhibits->get($action->scenario_evidence_id);
            if ($exhibit) {
                $excerpt = preg_replace('/^[\s“«"]+|[\s”»".!?,]+$/u', '', $content);
                if ($excerpt === '' || mb_stripos($exhibit->annotatableText(), $excerpt) !== false || $excerpt === $exhibit->title || $excerpt === $exhibit->summary) {
                    continue;
                }
            }
            $normalized = str_replace(["'", '’', '‘', 'ʻ', 'ʼ', '`'], '', mb_strtolower($content));
            preg_match_all('/[\p{L}\p{M}\p{N}]+/u', $normalized, $matches);
            $words = array_unique($matches[0]);
            if (count($words) < 2 || ! preg_match('/\p{L}/u', $normalized)) {
                continue;
            }
            $fingerprint = implode(' ', $words);
            $contributions[$fingerprint] ??= $action;
            if ($exhibit) {
                $citations[$fingerprint] ??= $action;
            }
        }

        return [collect(array_values($contributions)), collect(array_values($citations))->unique('scenario_evidence_id')->values()];
    }

    /**
     * @param  Collection<int, SessionAction>  $actions
     * @param  Collection<int, SessionAction>  $substantive
     * @param  Collection<int, SessionAction>  $citations
     * @param  Collection<int, ScenarioEvidence>  $exhibits
     * @return array{0: float, 1: list<int>, 2: list<int>, 3: string}
     */
    private function criterionEvidence(
        string $key,
        SimulationSession $session,
        Collection $actions,
        Collection $substantive,
        Collection $citations,
        Collection $exhibits,
    ): array {
        $arguments = $substantive->whereIn('action_type', ['make_statement', 'make_objection', 'present_evidence']);
        $presentations = $actions->where('action_type', 'present_evidence')->whereIn('scenario_evidence_id', $exhibits->keys())->unique('scenario_evidence_id');
        $questions = $substantive->where('action_type', 'ask_question');
        $objections = $substantive->where('action_type', 'make_objection');
        $stageEvents = $session->events->where('type', 'stage.changed');
        $stageCount = max(1, $session->version->stages->count() - 1);
        $distinctStages = $substantive->pluck('stage_key')->unique()->count();
        $citationTarget = min(2, max(1, $exhibits->count()));

        return match ($key) {
            'legal_knowledge' => [
                min(0.6, $substantive->count() / 6),
                $substantive->pluck('id')->all(),
                [],
                'Mustaqil bildirilgan fikrlar hisoblandi. Huquqiy aniqlikni o‘qituvchi tekshiradi. Avtomatik ball chegarasi 60%.',
            ],
            'legal_reasoning' => [
                ($arguments->isNotEmpty() ? 0.2 : 0) + 0.5 * min(1, $citations->count() / $citationTarget),
                $arguments->take(1)->concat($citations->take($citationTarget))->pluck('id')->unique()->values()->all(),
                [],
                'Pozitsiyani bayon qilish uchun 20%, alohida dalillarga bog‘langan izohlar uchun 50% gacha hisoblanadi. Asosning to‘g‘riligini o‘qituvchi tekshiradi.',
            ],
            'evidence_analysis' => [
                ($presentations->count() + $citations->count()) / (2 * max(1, $exhibits->count())),
                $presentations->pluck('id')->concat($citations->pluck('id'))->unique()->values()->all(),
                [],
                'Ochiq dalillarni taqdim etish va ularga o‘z so‘zingiz bilan izoh berish alohida hisoblanadi.',
            ],
            'procedure' => [
                min(1, $stageEvents->count() / $stageCount),
                $actions->where('action_type', 'advance_stage')->pluck('id')->all(),
                $stageEvents->pluck('id')->all(),
                'Server tasdiqlagan bosqich o‘tishlari ssenariydagi protsessual oqimga nisbatan hisoblandi.',
            ],
            'questioning' => [
                min(1, $questions->count() / 2),
                $questions->pluck('id')->all(),
                [],
                'Guvohni va qarama-qarshi pozitsiyani tekshirish uchun berilgan savollar hisoblandi.',
            ],
            'speech' => [
                min(0.8, $substantive->count() / 4),
                $substantive->pluck('id')->all(),
                [],
                'Har bir alohida fikr bir marta hisoblanadi. Nutq uslubi va aniqligini o‘qituvchi baholaydi.',
            ],
            'critical_thinking' => [
                min(0.8, ($questions->count() + $objections->count()) / 3),
                $questions->merge($objections)->pluck('id')->unique()->values()->all(),
                [],
                'Savol va e’tiroz harakatlari qarama-qarshi pozitsiyani tekshirish indikatori sifatida olindi.',
            ],
            'strategy' => [
                min(0.8, $distinctStages / max(1, $stageCount)),
                $substantive->pluck('id')->all(),
                [],
                'Talaba faol bo‘lgan turli bosqichlar ulushi hisoblandi; strategiyaning mazmuni inson tomonidan baholanadi.',
            ],
            'ethics' => [
                $substantive->isNotEmpty() ? 0.7 : 0.2,
                $substantive->pluck('id')->all(),
                [],
                'Tizim faqat ruxsat etilgan harakatlarni qayd etdi; professional etika bo‘yicha yakuniy xulosani o‘qituvchi beradi.',
            ],
            default => [0, [], [], 'Ushbu mezon uchun avtomatik indikator sozlanmagan.'],
        };
    }
}
