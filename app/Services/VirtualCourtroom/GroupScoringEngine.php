<?php

namespace App\Services\VirtualCourtroom;

use App\Models\SessionParticipant;
use App\Models\SimulationSession;
use Illuminate\Support\Str;

class GroupScoringEngine
{
    public function __construct(private GroupAssessmentRubric $rubric, private GroupAssessmentAdvisor $advisor) {}

    /** @return array<string, mixed> */
    public function snapshot(array $config = []): array
    {
        return $this->rubric->snapshot($config);
    }

    /**
     * Normalizes any snapshot or legacy rubric array into a complete, guaranteed data structure.
     *
     * @param  array<string, mixed>|mixed  $snapshot
     * @return array<string, mixed>
     */
    public function normalizeSnapshot(mixed $snapshot): array
    {
        $default = $this->rubric->snapshot([]);

        if (! is_array($snapshot) || empty($snapshot)) {
            return $default;
        }

        $team = (isset($snapshot['team']) && is_array($snapshot['team']) && ! empty($snapshot['team']))
            ? $snapshot['team']
            : $default['team'];

        $roles = (isset($snapshot['roles']) && is_array($snapshot['roles']) && ! empty($snapshot['roles']))
            ? $snapshot['roles']
            : $default['roles'];

        $tieThreshold = isset($snapshot['tie_threshold']) && is_numeric($snapshot['tie_threshold'])
            ? (float) $snapshot['tie_threshold']
            : (float) $default['tie_threshold'];

        $academic = (isset($snapshot['academic']) && is_array($snapshot['academic']) && ! empty($snapshot['academic']))
            ? $snapshot['academic']
            : $default['academic'];

        $gradebook = (isset($snapshot['gradebook']) && is_array($snapshot['gradebook']) && ! empty($snapshot['gradebook']))
            ? $snapshot['gradebook']
            : $default['gradebook'];

        $version = is_string($snapshot['version'] ?? null) && ! empty($snapshot['version'])
            ? $snapshot['version']
            : $default['version'];

        return [
            'version' => $version,
            'team' => $team,
            'roles' => $roles,
            'tie_threshold' => $tieThreshold,
            'academic' => $academic,
            'gradebook' => $gradebook,
        ];
    }

    /** @return array{status:string,score:?float,criteria:array,evidence:array,message:?string} */
    public function recommend(SimulationSession $session, SessionParticipant $participant): array
    {
        return $this->advisor->recommend($session, $participant);
    }

    /** @return array<string,array<string,mixed>> */
    public function evidenceFor(SimulationSession $session, SessionParticipant $participant): array
    {
        return $this->advisor->evidenceFor($session, $participant);
    }

    /**
     * Computes the trial outcome with a robust, validated data contract.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  list<array{student_id:int,role_key:string,status:string,criteria:array<string,float>,evidence:array<string,mixed>}>  $rows
     * @param  array<string, mixed>  $context  Optional session context (e.g. saved_winner, is_demo, etc.)
     * @return array{
     *     winner: string,
     *     winner_source: string,
     *     score_status: string,
     *     prosecution_score: ?float,
     *     defense_score: ?float,
     *     reason: string,
     *     teams: array{prosecution: array, defense: array},
     *     awards: list<array>,
     *     saved_winner: ?string
     * }
     */
    public function outcome(array $snapshot, array $rows, array $context = []): array
    {
        $normalizedSnapshot = $this->normalizeSnapshot($snapshot);

        // 9 active roles distribution:
        // Prosecution: Prosecutor, Victim Attorney, Victim
        // Defense: Defense Attorney, Defendant
        // Neutral: Judge, Witness 1, Witness 2, Witness 3 (excluded from team scores)
        $prosecution = $this->team($normalizedSnapshot, $rows, ['prosecutor', 'victim_advocate'], 'victim');
        $defense = $this->team($normalizedSnapshot, $rows, ['defense_advocate'], 'defendant');
        $teams = ['prosecution' => $prosecution, 'defense' => $defense];

        $savedWinner = is_string($context['saved_winner'] ?? null) && in_array($context['saved_winner'], ['prosecution', 'defense', 'tie'], true)
            ? $context['saved_winner']
            : null;
        $savedWinnerLabel = is_string($context['saved_winner_label'] ?? null) ? $context['saved_winner_label'] : null;

        $prosecutionScore = $prosecution['score'];
        $defenseScore = $defense['score'];

        if ($prosecutionScore !== null && $defenseScore !== null) {
            $difference = round($prosecutionScore - $defenseScore, 2);
            $tieThreshold = (float) $normalizedSnapshot['tie_threshold'];

            if (abs($difference) <= $tieThreshold) {
                $winner = 'tie';
                $reason = 'Tomonlarning ballari orasidagi farq '.abs($difference).' ball. Durang chegarasi '.$tieThreshold.' ball.';
            } else {
                $winner = $difference > 0 ? 'prosecution' : 'defense';
                $other = $winner === 'prosecution' ? 'defense' : 'prosecution';
                $contributions = [];

                foreach ($normalizedSnapshot['team'] as $key => $criterion) {
                    $wScore = $teams[$winner]['criteria'][$key] ?? 0;
                    $oScore = $teams[$other]['criteria'][$key] ?? 0;
                    $weight = (float) ($criterion['weight'] ?? 1);
                    $contributions[$key] = ($wScore - $oScore) * $weight / 100;
                }

                arsort($contributions);
                $topKey = array_key_first($contributions);
                $topLabel = $normalizedSnapshot['team'][$topKey]['label'] ?? Str::headline((string) $topKey);
                $reason = 'Bir xil mezonlar va ulushlar bo‘yicha ustunlik '.abs($difference).' ball. Eng katta farq keltirgan mezon — '.$topLabel.'.';
            }

            return [
                'winner' => $winner,
                'winner_source' => 'calculated',
                'score_status' => 'CALCULATED',
                'prosecution_score' => $prosecutionScore,
                'defense_score' => $defenseScore,
                'reason' => $reason,
                'teams' => $teams,
                'awards' => $this->awards($normalizedSnapshot, $rows),
                'saved_winner' => $savedWinner,
            ];
        }

        // Scores could not be calculated due to missing evidence or criteria
        $hasAnyAdvocate = ($prosecution['advocates_count'] + $defense['advocates_count']) > 0;
        $scoreStatus = $hasAnyAdvocate ? 'INSUFFICIENT_DATA' : 'NOT_CALCULATED';

        if ($savedWinner !== null) {
            $reasonText = $savedWinnerLabel ?: match ($savedWinner) {
                'prosecution' => 'Sud majlisi yakuniga ko‘ra saqlangan natija: Ayblov tomoni g‘olib.',
                'defense' => 'Sud majlisi yakuniga ko‘ra saqlangan natija: Himoya tomoni g‘olib.',
                'tie' => 'Sud majlisi yakuniga ko‘ra saqlangan natija: Durang.',
                default => 'Natija saqlangan.',
            };

            return [
                'winner' => $savedWinner,
                'winner_source' => 'preserved_verdict',
                'score_status' => $scoreStatus,
                'prosecution_score' => $prosecutionScore,
                'defense_score' => $defenseScore,
                'reason' => $reasonText,
                'teams' => $teams,
                'awards' => $this->awards($normalizedSnapshot, $rows),
                'saved_winner' => $savedWinner,
            ];
        }

        return [
            'winner' => 'undetermined',
            'winner_source' => 'undetermined',
            'score_status' => $scoreStatus,
            'prosecution_score' => $prosecutionScore,
            'defense_score' => $defenseScore,
            'reason' => 'Ikkala tomonning barcha biriktirilgan huquqiy vakillari uchun olti mezon bo‘yicha dalilli baholar kerak.',
            'teams' => $teams,
            'awards' => $this->awards($normalizedSnapshot, $rows),
            'saved_winner' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $advocateRoles
     * @return array{
     *     score: ?float,
     *     status: string,
     *     criteria: array<string, ?float>,
     *     strengths: list<string>,
     *     weaknesses: list<string>,
     *     advocates_count: int,
     *     party_score: ?float,
     *     party_role: string,
     *     roles_evaluated: list<string>,
     *     evidence: array<string, list<string>>
     * }
     */
    private function team(array $snapshot, array $rows, array $advocateRoles, string $partyRole): array
    {
        $advocates = array_values(array_filter($rows, fn (array $row): bool => in_array($row['role_key'] ?? '', $advocateRoles, true)));
        $criteria = [];
        $strengths = [];
        $weaknesses = [];
        $weighted = 0.0;
        $allCriteriaCalculated = true;
        $evaluatedRoles = [];
        $combinedEvidence = [];

        foreach ($snapshot['team'] as $key => $definition) {
            $percentages = [];
            $combinedEvidence[$key] = [];

            foreach ($advocates as $row) {
                $percentage = $this->reviewedPercentage($snapshot, $row, (string) $key);
                if ($percentage !== null) {
                    $percentages[] = $percentage;
                    $evaluatedRoles[] = (string) ($row['role_key'] ?? '');
                }

                $evList = $row['evidence'][$key] ?? null;
                if (is_array($evList)) {
                    $combinedEvidence[$key] = array_merge($combinedEvidence[$key], array_filter($evList, 'is_string'));
                }
            }

            $criteria[$key] = null;
            if ($percentages === [] || count($percentages) !== count($advocates)) {
                $allCriteriaCalculated = false;

                continue;
            }

            $mean = array_sum($percentages) / count($percentages);
            $criteria[$key] = round($mean, 2);
            $weighted += $mean * (float) ($definition['weight'] ?? 0) / 100;

            if ($mean >= 75) {
                $strengths[] = $definition['label'] ?? (string) $key;
            }
            if ($mean < 60) {
                $weaknesses[] = $definition['label'] ?? (string) $key;
            }
        }

        $party = collect($rows)->firstWhere('role_key', $partyRole);
        $partyScore = null;
        if (is_array($party)) {
            $partyScore = $this->completeScore($snapshot, $party);
            if ($partyScore !== null) {
                $evaluatedRoles[] = $partyRole;
            }
        }

        $hasAdvocates = count($advocates) > 0;
        $isComplete = $hasAdvocates && $allCriteriaCalculated && ! in_array(null, $criteria, true);

        return [
            'score' => $isComplete ? round($weighted, 2) : null,
            'status' => $isComplete ? 'CALCULATED' : ($hasAdvocates ? 'INSUFFICIENT_DATA' : 'NOT_CALCULATED'),
            'criteria' => $criteria,
            'strengths' => array_values(array_unique($strengths)),
            'weaknesses' => array_values(array_unique($weaknesses)),
            'advocates_count' => count($advocates),
            'party_score' => $partyScore,
            'party_role' => $partyRole,
            'roles_evaluated' => array_values(array_unique(array_filter($evaluatedRoles))),
            'evidence' => $combinedEvidence,
        ];
    }

    private function reviewedPercentage(array $snapshot, array $row, string $criterion): ?float
    {
        $role = $this->rubricRole($row);
        $max = $snapshot['roles'][$role][$criterion]['max'] ?? 0;
        $score = $row['criteria'][$criterion] ?? null;

        if (! in_array($row['status'] ?? '', ['teacher_reviewed', 'approved'], true)
            || $max <= 0
            || ! is_numeric($score)
            || ! is_finite((float) $score)
            || (float) $score < 0
            || (float) $score > $max) {
            return null;
        }

        $evidence = $row['evidence'][$criterion] ?? null;
        if (empty($evidence)) {
            // Check if row has valid non-empty evidence
            if (is_array($row['evidence'] ?? null) && ! empty($row['evidence']['valid']) && (int) $row['evidence']['valid'] > 0) {
                // Summary evidence format
            } else {
                return null;
            }
        }

        return 100 * (float) $score / $max;
    }

    private function completeScore(array $snapshot, array $row): ?float
    {
        $role = $this->rubricRole($row);
        $criteria = $snapshot['roles'][$role] ?? [];

        if ($criteria === []) {
            return null;
        }

        foreach (array_keys($criteria) as $key) {
            if ($this->reviewedPercentage($snapshot, $row, (string) $key) === null) {
                return null;
            }
        }

        return round(array_sum(array_intersect_key($row['criteria'] ?? [], $criteria)), 2);
    }

    private function rubricRole(array $row): string
    {
        $roleKey = (string) ($row['role_key'] ?? '');

        if (str_starts_with($roleKey, 'observer_') || $roleKey === 'observer') {
            return 'observer';
        }

        if (in_array($roleKey, ['witness_1', 'witness_2', 'witness_3'], true)) {
            return 'witness';
        }

        return $roleKey;
    }

    /** @return list<array{key:string,label:string,student_id:int,role_key:string,evidence:list<string>}> */
    private function awards(array $snapshot, array $rows): array
    {
        $advocates = ['prosecutor', 'victim_advocate', 'defense_advocate'];
        $categories = [
            'argument' => ['Eng yaxshi huquqiy argument', 'legal', $advocates],
            'speech' => ['Eng yaxshi nutq', 'closing', $advocates],
            'question' => ['Eng yaxshi savol', 'questions', $advocates],
            'evidence_analysis' => ['Eng yaxshi dalillar tahlili', 'evidence', [...$advocates, 'judge', 'observer']],
            'defense_strategy' => ['Eng yaxshi himoya strategiyasi', 'legal', ['defense_advocate']],
            'procedure' => ['Eng yaxshi protsessual boshqaruv', 'procedure', ['judge']],
            'observer' => ['Eng faol kuzatuvchi', null, ['observer']],
        ];
        $awards = [];

        foreach ($categories as $awardKey => [$label, $criterion, $roles]) {
            $leaders = [];
            $best = 80.0;

            foreach ($rows as $row) {
                if (! in_array($this->rubricRole($row), $roles, true)) {
                    continue;
                }

                $score = $criterion === null
                    ? $this->completeScore($snapshot, $row)
                    : $this->reviewedPercentage($snapshot, $row, (string) $criterion);

                if ($score === null || round($score, 2) < $best) {
                    continue;
                }

                $score = round($score, 2);
                if ($score > $best) {
                    $leaders = [];
                    $best = $score;
                }

                $rawEvidence = $row['evidence'] ?? [];
                $refs = [];

                if ($criterion === null) {
                    if (is_array($rawEvidence)) {
                        foreach ($rawEvidence as $val) {
                            if (is_array($val)) {
                                $refs = array_merge($refs, array_filter($val, 'is_string'));
                            } elseif (is_string($val)) {
                                $refs[] = $val;
                            }
                        }
                    }
                } else {
                    $cEvidence = $rawEvidence[$criterion] ?? [];
                    if (is_array($cEvidence)) {
                        $refs = array_filter($cEvidence, 'is_string');
                    } elseif (is_string($cEvidence)) {
                        $refs = [$cEvidence];
                    }
                }

                $leaders[] = [
                    'key' => $awardKey,
                    'label' => $label,
                    'student_id' => (int) ($row['student_id'] ?? 0),
                    'role_key' => (string) ($row['role_key'] ?? ''),
                    'evidence' => array_values(array_unique($refs)),
                ];
            }

            array_push($awards, ...$leaders);
        }

        return $awards;
    }
}
