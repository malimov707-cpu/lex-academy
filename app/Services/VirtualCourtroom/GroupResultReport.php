<?php

namespace App\Services\VirtualCourtroom;

use App\Enums\UserRole;
use App\Models\CourtroomParticipantGrade;
use App\Models\SessionParticipant;
use App\Models\SimulationSession;
use App\Models\User;

class GroupResultReport
{
    public function __construct(private GroupScoringEngine $scoring) {}

    public static function roleLabel(string $role): string
    {
        return __(match ($role) {
            'judge' => 'Sudya',
            'prosecutor' => 'Prokuror',
            'victim_advocate' => 'Jabrlanuvchi advokati',
            'victim' => 'Jabrlanuvchi',
            'defense_advocate' => 'Ayblanuvchi advokati',
            'defendant' => 'Ayblanuvchi',
            'witness_1' => 'Birinchi guvoh',
            'witness_2' => 'Ikkinchi guvoh',
            'witness_3' => 'Uchinchi guvoh',
            default => 'Kuzatuvchi',
        });
    }

    public static function winnerLabel(string $winner): string
    {
        return __(match ($winner) {
            'prosecution' => 'Ayblov tomoni g‘olib',
            'defense' => 'Himoya tomoni g‘olib',
            'tie' => 'Durang',
            default => 'Natija aniqlanmadi',
        });
    }

    public static function statusLabel(string $status): string
    {
        return __(match ($status) {
            'approved' => 'Tasdiqlangan',
            'teacher_reviewed' => 'O‘qituvchi ko‘rib chiqdi',
            'returned_for_revision' => 'Qayta ko‘rib chiqilmoqda',
            default => 'Dastlabki tahlil',
        });
    }

    public static function scoreStatusLabel(string $status): string
    {
        return __(match ($status) {
            'CALCULATED' => 'Hisoblangan',
            'INSUFFICIENT_DATA' => 'Baholash ma’lumotlari yetarli emas',
            default => 'Hisoblanmagan',
        });
    }

    /** @return array<string, mixed> */
    public function forViewer(SimulationSession $session, User $viewer): array
    {
        abort_unless($session->education_group_id, 404);
        $teacher = $session->isManagedGroupBy($viewer);
        $roster = $session->participants()->whereNotNull('user_id')->orderBy('id')->get();
        $own = $roster->firstWhere('user_id', $viewer->id);
        abort_unless($teacher || ($viewer->hasRole(UserRole::Student) && $own), 403);
        abort_unless($session->status === 'completed', 409, __('Mashg‘ulot hali yakunlanmagan.'));

        $settings = is_array($session->settings) ? $session->settings : [];
        $rubric = $this->scoring->normalizeSnapshot($settings['assessment_rubric'] ?? []);
        $assessment = is_array($settings['assessment'] ?? null) ? $settings['assessment'] : [];

        $allGrades = CourtroomParticipantGrade::query()
            ->where('simulation_session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        $status = $assessment['status'] ?? null;
        if ($status === null) {
            $allApproved = $allGrades->isNotEmpty() && $allGrades->every(fn ($g): bool => $g->status === 'approved');
            $status = $allApproved ? 'approved' : 'preliminary';
        }
        $published = $status === 'approved';

        $grades = $teacher
            ? $allGrades
            : $allGrades->filter(fn ($grade): bool => $grade->student_id === $viewer->id && $grade->status === 'approved');

        $showScores = $teacher || $published;
        $outcome = null;

        if ($showScores) {
            $context = [
                'saved_winner' => $settings['winner'] ?? ($assessment['outcome']['winner'] ?? null),
                'saved_winner_label' => $settings['winner_label'] ?? null,
                'is_demo' => ! empty($settings['is_demo']),
                'winner_source' => isset($settings['winner']) ? 'preserved_verdict' : null,
            ];

            if ($published && isset($assessment['outcome']) && is_array($assessment['outcome'])) {
                $outcome = $assessment['outcome'];
            } else {
                $outcomeRows = $allGrades->values()->map(fn ($grade): array => $grade->only(['student_id', 'role_key', 'criteria', 'evidence', 'status']))->all();
                $outcome = $this->scoring->outcome($rubric, $outcomeRows, $context);
            }

            if (! $teacher && $outcome && isset($outcome['awards'])) {
                foreach ($outcome['awards'] as &$award) {
                    unset($award['evidence']);
                }
                unset($award);
            }
        }

        $names = $roster->pluck('display_name', 'user_id');
        if ($outcome && ! empty($outcome['awards'])) {
            foreach ($outcome['awards'] as &$award) {
                $award['name'] = $names->get($award['student_id']);
            }
            unset($award);
        }

        $visible = $teacher ? $roster : collect([$own]);
        $people = $visible->map(function (SessionParticipant $participant) use ($grades, $showScores, $teacher): array {
            $grade = $showScores ? $grades->get($participant->user_id) : null;
            $row = [
                'student_id' => $participant->user_id,
                'name' => $participant->display_name,
                'role_key' => $participant->role_key,
                'role' => self::roleLabel($participant->role_key),
                'observer' => $participant->actor_type === 'observer',
                'attended_at' => data_get($participant->metadata, 'attended_at'),
                'grade' => $grade?->only([
                    'criteria', 'activity', 'evidence', 'ai_recommendation', 'attendance', 'individual_score',
                    'activity_score', 'simulation_performance', 'teacher_final_grade', 'strengths', 'improvements',
                    'comment', 'recommendation', 'rubric_version', 'status', 'approved_at', 'reassessment',
                ]),
            ];

            if (! $teacher && $grade) {
                $row['grade']['reassessment'] = collect($grade->reassessment)->only(['message', 'requested_at', 'status', 'response'])->all();
            }

            return $row;
        })->values()->all();

        $observers = $roster->where('actor_type', 'observer')->count();
        $session->loadMissing(['scenario:id,title', 'version:id,version', 'educationGroup:id,name', 'course:id,title']);

        return [
            'session' => [
                'id' => $session->id,
                'scenario' => $session->scenario?->title ?? __('Noma’lum ssenariy'),
                'scenario_version' => $session->version?->version ?? '1.0',
                'group_id' => $session->education_group_id,
                'group' => $session->educationGroup?->name ?? __('Noma’lum guruh'),
                'course' => $session->course?->title ?? __('Noma’lum kurs'),
                'status' => $session->status,
                'completed_at' => $session->completed_at?->toIso8601String(),
                'duration_seconds' => $session->elapsed_seconds ?? 0,
                'participants' => $roster->count(),
                'active' => $roster->count() - $observers,
                'observers' => $observers,
                'attended' => $roster->filter(fn ($person) => data_get($person->metadata, 'attended_at'))->count(),
            ],
            'teacher' => $teacher,
            'published' => $published,
            'status' => $status,
            'version' => $session->lock_version,
            'rubric' => $rubric,
            'people' => $people,
            'outcome' => $outcome,
            'conclusion' => $showScores ? ($assessment['conclusion'] ?? []) : [],
        ];
    }
}
