<?php

namespace App\Services\VirtualCourtroom;

use App\Enums\UserRole;
use App\Models\ClassSchedule;
use App\Models\EducationGroup;
use App\Models\ScenarioEvidence;
use App\Models\SessionParticipant;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupCourtroomSession
{
    public const ROLES = [
        'judge' => 'Sudya', 'prosecutor' => 'Prokuror', 'victim_advocate' => 'Jabrlanuvchi advokati',
        'victim' => 'Jabrlanuvchi', 'defense_advocate' => 'Ayblanuvchi advokati', 'defendant' => 'Ayblanuvchi',
        'witness_1' => 'Birinchi guvoh', 'witness_2' => 'Ikkinchi guvoh', 'witness_3' => 'Uchinchi guvoh',
    ];

    public const OBSERVATIONS = [
        'observe_stage' => 'Bosqich bo‘yicha qayd', 'identify_error' => 'Huquqiy xatoni tahlil qilish',
        'analyze_evidence' => 'Dalillar tahlili', 'compare_arguments' => 'Tomonlarning argumentlarini taqqoslash',
        'final_analysis' => 'Yakuniy yozma tahlil', 'peer_review' => 'Ishtirokchiga fikr bildirish',
    ];

    public function __construct(private SessionEventRecorder $events, private GroupScoringEngine $scoring) {}

    public function groups(User $user): Builder
    {
        return EducationGroup::query()->whereHas('course', function (Builder $courses) use ($user): void {
            if (! $user->isAdmin()) {
                $courses->managedBy($user);
            }
        });
    }

    public function scenarios(User $user): Builder
    {
        return SimulationScenario::query()->published()->whereHas('versions', fn (Builder $q): Builder => $q->where('status', 'published'))
            ->when(! $user->isAdmin(), fn (Builder $q): Builder => $q->where('created_by', $user->id));
    }

    /** @param array<string, mixed> $data */
    public function create(User $teacher, array $data): SimulationSession
    {
        return DB::transaction(function () use ($teacher, $data): SimulationSession {
            $group = $this->groups($teacher)->lockForUpdate()->findOrFail($data['group_id']);
            $scenario = $this->scenarios($teacher)->findOrFail($data['scenario_id']);
            $existing = SimulationSession::query()->where('group_creation_key', $data['creation_key'])->first();
            if ($existing) {
                abort_unless($existing->facilitator_id === $teacher->id && $existing->education_group_id === $group->id && $existing->scenario_id === $scenario->id, 409);

                return $existing;
            }
            $version = $scenario->versions()->where('status', 'published')->orderByDesc('version')->firstOrFail();
            $members = $group->members()->where('role', UserRole::Student->value)->orderBy('users.id')->get();
            $assigned = collect($data['roles'])->filter()->map(fn ($id): int => (int) $id);
            if ($assigned->isEmpty() || $assigned->diff($members->modelKeys())->isNotEmpty() || $assigned->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['roles' => __('Har bir faol rolga guruhdan alohida talaba tanlang.')]);
            }
            if (! empty($data['class_schedule_id'])) {
                $validSchedule = ClassSchedule::query()->where('course_id', $group->course_id)->where('group_id', $group->id)
                    ->where('teacher_id', $teacher->id)->whereKey($data['class_schedule_id'])->exists();
                abort_unless($validSchedule, 422);
            }
            $rubric = $this->scoring->snapshot($data['rubric'] ?? []);
            $settings = [
                'assessment_rubric' => $rubric, 'class_schedule_id' => $data['class_schedule_id'] ?? null,
                'observer_quiz' => $data['quiz'] ?? [], 'group_name' => $group->name,
            ];
            $session = SimulationSession::query()->create([
                'facilitator_id' => $teacher->id, 'education_group_id' => $group->id, 'course_id' => $group->course_id,
                'group_creation_key' => $data['creation_key'], 'scenario_id' => $scenario->id, 'scenario_version_id' => $version->id,
                'selected_role_key' => 'group', 'status' => 'created', 'settings' => $settings,
            ]);
            $byMember = $assigned->flip();
            $scenarioRoles = $version->roles()->pluck('key');
            foreach ($members as $member) {
                $role = $byMember->get($member->id);
                $session->participants()->create([
                    'user_id' => $member->id, 'role_key' => $role ?? 'observer_'.$member->id,
                    'actor_type' => $role ? 'student' : 'observer', 'display_name' => $member->name, 'status' => 'assigned',
                    'metadata' => $role ? [
                        'scenario_role_key' => collect($this->scenarioRoleKeys($role))->first(fn (string $key): bool => $scenarioRoles->contains($key)),
                        'role_brief' => $data['briefs'][$role] ?? null,
                    ] : null,
                ]);
            }
            $this->events->record($session, 'group.assigned', payload: ['facilitator_id' => $teacher->id, 'rubric_version' => $rubric['version']]);

            return $session;
        }, 3);
    }

    /** @return array<string,string> */
    public function actionsFor(SessionParticipant $participant): array
    {
        if ($participant->actor_type === 'observer') {
            return self::OBSERVATIONS;
        }
        $common = ['make_statement' => 'Fikr bildirish', 'present_evidence' => 'Dalilni taqdim etish', 'answer_question' => 'Savolga javob'];
        if (str_starts_with($participant->role_key, 'witness_') || in_array($participant->role_key, ['defendant', 'victim'], true)) {
            return $common;
        }
        $common['ask_question'] = 'Savol berish';
        if ($participant->role_key === 'judge') {
            return $common + ['procedural_ruling' => 'Protsessual masalani hal etish', 'final_decision' => 'Qarorni asoslash'];
        }

        return $common + ['make_objection' => 'E’tiroz bildirish', 'rebuttal' => 'Qarshi dalil keltirish', 'closing_statement' => 'Yakuniy nutq'];
    }

    /** @return list<string> */
    public function scenarioRoleKeys(string $role): array
    {
        return match ($role) {
            'defense_advocate' => [$role, 'defense_attorney'],
            'victim_advocate' => [$role, 'victim_attorney', 'prosecutor'],
            'victim' => [$role, 'prosecutor'],
            'defendant' => [$role, 'defense_attorney'],
            'witness_1', 'witness_2', 'witness_3' => [$role, 'witness'],
            default => [$role],
        };
    }

    public function visibleEvidence(SimulationSession $session, ?SessionParticipant $participant): Collection
    {
        $evidence = $session->version->evidence()->where('status', 'active')->orderBy('sort_order')->get();
        if (! $participant) {
            return $evidence;
        }
        $keys = $this->scenarioRoleKeys($participant->role_key);

        return $evidence->filter(fn (ScenarioEvidence $item): bool => collect($keys)->contains(fn (string $key): bool => $item->isVisibleTo($key)))->values();
    }

    public function attend(SimulationSession $session, User $user): void
    {
        DB::transaction(function () use ($session, $user): void {
            $locked = SimulationSession::query()->whereNotNull('education_group_id')->lockForUpdate()->findOrFail($session->id);
            $member = $locked->participants()->where('user_id', $user->id)->firstOrFail();
            abort_unless(in_array($locked->status, ['created', 'active'], true), 409);
            $this->markPresent($locked, $member);
        }, 3);
    }

    private function markPresent(SimulationSession $session, SessionParticipant $member): void
    {
        if (isset($member->metadata['attended_at'])) {
            return;
        }
        $member->update(['status' => 'present', 'metadata' => [...($member->metadata ?? []), 'attended_at' => now()->toIso8601String()]]);
        $this->events->record($session, 'participant.attended', $member->role_key, $session->current_stage_key, ['user_id' => $member->user_id]);
    }

    /** @param array<string, mixed> $data */
    public function act(SimulationSession $session, User $user, array $data): void
    {
        DB::transaction(function () use ($session, $user, $data): void {
            $locked = SimulationSession::query()->whereNotNull('education_group_id')->lockForUpdate()->findOrFail($session->id);
            $member = $locked->participants()->where('user_id', $user->id)->firstOrFail();
            $previous = $locked->actions()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($previous) {
                abort_unless($previous->actor_id === $user->id, 409);

                return;
            }
            abort_unless($locked->status === 'active' && $locked->current_stage_key === $data['stage_key'], 409, __('Sessiya bosqichi o‘zgardi. Sahifani yangilang.'));
            $type = $data['action_type'];
            $quiz = $member->actor_type === 'observer' && $type === 'observer_quiz';
            abort_unless($quiz || array_key_exists($type, $this->actionsFor($member)), 422);
            $metadata = [];
            $content = trim((string) ($data['content'] ?? ''));
            $target = null;
            $evidenceId = null;
            if ($quiz) {
                $questionId = $data['question_id'] ?? null;
                $question = $locked->settings['observer_quiz'][$questionId] ?? null;
                $answer = $data['answer'] ?? null;
                abort_unless($question && $answer !== null && array_key_exists($answer, $question['options']), 422);
                abort_if($locked->actions()->where('actor_id', $user->id)->where('action_type', 'observer_quiz')->where('evaluation_metadata->question_id', (int) $questionId)->exists(), 409);
                $metadata = ['question_id' => (int) $questionId, 'answer' => $answer, 'quiz_correct' => $answer === $question['correct']];
                $content = $question['question'].' — '.$question['options'][$answer];
            } elseif ($content === '') {
                throw ValidationException::withMessages(['content' => __('Nutq yoki tahlil matnini kiriting.')]);
            }
            if (in_array($type, ['present_evidence', 'analyze_evidence'], true)) {
                $evidence = $this->visibleEvidence($locked, $member)->firstWhere('id', $data['evidence_id'] ?? null);
                abort_unless($evidence, 422, __('Mavjud dalillardan birini tanlang.'));
                $evidenceId = $evidence->id;
            }
            if (in_array($type, ['ask_question', 'peer_review'], true)) {
                $target = $locked->participants()->where('actor_type', 'student')->where('user_id', '!=', $user->id)
                    ->where('role_key', $data['target_role_key'] ?? null)->first();
                abort_unless($target, 422, __('Guruhdagi boshqa faol ishtirokchini tanlang.'));
            }
            $this->markPresent($locked, $member);
            $action = $locked->actions()->create([
                'actor_id' => $user->id, 'actor_role_key' => $member->role_key, 'stage_key' => $locked->current_stage_key,
                'action_type' => $type, 'target_role_key' => $target?->role_key, 'scenario_evidence_id' => $evidenceId,
                'content' => $content, 'validation_status' => 'accepted', 'evaluation_metadata' => $metadata, 'idempotency_key' => $data['idempotency_key'],
            ]);
            if ($member->actor_type === 'student') {
                $locked->turns()->create([
                    'sequence' => ((int) $locked->turns()->max('sequence')) + 1,
                    'speaker_role_key' => $member->role_key, 'speaker_type' => 'student', 'input_type' => 'text', 'content' => $content,
                    'metadata' => ['user_id' => $user->id, 'action_id' => $action->id, 'stage_key' => $locked->current_stage_key],
                ]);
            }
            $this->events->record($locked, 'action.accepted', $member->role_key, $locked->current_stage_key, [
                'action_id' => $action->id, 'action_type' => $type, 'user_id' => $user->id, 'evidence_id' => $evidenceId,
            ]);
            $settings = $locked->settings ?? [];
            if ($type === 'present_evidence' && $evidenceId) {
                $settings['board_evidence_id'] = $evidenceId;
            }
            $locked->update(['settings' => $settings, 'last_activity_at' => now(), 'lock_version' => $locked->lock_version + 1]);
        }, 3);
    }

    public function transition(SimulationSession $session, User $teacher, string $operation, ?string $fromStage = null, ?string $nextStage = null): SimulationSession
    {
        return DB::transaction(function () use ($session, $teacher, $operation, $fromStage, $nextStage): SimulationSession {
            $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($locked->isManagedGroupBy($teacher), 403);
            $stages = $locked->version->stages()->get();
            $now = now();
            if ($operation === 'start') {
                if ($locked->status === 'active') {
                    return $locked;
                }
                abort_unless($locked->status === 'created' && $stages->isNotEmpty(), 409);
                $changes = ['status' => 'active', 'started_at' => $now, 'current_stage_key' => $stages->first()->key];
                $event = 'session.started';
            } elseif ($operation === 'pause') {
                if ($locked->status === 'paused') {
                    return $locked;
                }
                abort_unless(in_array($locked->status, ['created', 'active'], true), 409);
                $changes = ['status' => 'paused'];
                $event = 'session.paused';
            } elseif ($operation === 'resume') {
                if ($locked->status === 'active') {
                    return $locked;
                }
                abort_unless($locked->status === 'paused', 409);
                $changes = ['status' => 'active'];
                $event = 'session.resumed';
            } elseif ($operation === 'stage') {
                $current = $stages->firstWhere('key', $locked->current_stage_key);
                abort_unless(in_array($locked->status, ['active', 'paused'], true) && $current && $current->key === $fromStage, 409);
                abort_unless(in_array($nextStage, $current->allowed_next_stages ?? [], true) && $stages->contains('key', $nextStage), 422);
                $changes = ['current_stage_key' => $nextStage];
                $event = 'stage.changed';
            } else {
                if ($locked->status === 'completed') {
                    return $locked;
                }
                abort_unless(in_array($locked->status, ['active', 'paused'], true), 409);
                $changes = ['status' => 'completed', 'completed_at' => $now, 'elapsed_seconds' => (int) $locked->started_at->diffInSeconds($now)];
                $event = 'session.completed';
            }
            $locked->update([...$changes, 'last_activity_at' => $now, 'lock_version' => $locked->lock_version + 1]);
            $this->events->record($locked, $event, 'facilitator', $locked->current_stage_key, ['user_id' => $teacher->id]);

            return $locked;
        }, 3);
    }

    public function presentEvidence(SimulationSession $session, User $teacher, int $evidenceId): SimulationSession
    {
        return DB::transaction(function () use ($session, $teacher, $evidenceId): SimulationSession {
            $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($locked->isManagedGroupBy($teacher), 403);
            $evidence = $locked->version->evidence()->where('status', 'active')->findOrFail($evidenceId);
            $settings = $locked->settings ?? [];
            $settings['board_evidence_id'] = $evidence->id;
            $locked->update(['settings' => $settings, 'last_activity_at' => now(), 'lock_version' => $locked->lock_version + 1]);
            $this->events->record($locked, 'evidence.presented', 'facilitator', $locked->current_stage_key, [
                'evidence_id' => $evidence->id,
                'title' => $evidence->title,
                'user_id' => $teacher->id,
            ]);

            return $locked;
        }, 3);
    }
}
