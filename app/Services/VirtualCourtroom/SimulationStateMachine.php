<?php

namespace App\Services\VirtualCourtroom;

use App\Exceptions\InvalidSimulationAction;
use App\Models\ScenarioActionRule;
use App\Models\ScenarioEvidence;
use App\Models\ScenarioStage;
use App\Models\SessionAction;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SimulationStateMachine
{
    public function __construct(private SessionEventRecorder $events) {}

    public function start(SimulationSession $session): SimulationSession
    {
        return DB::transaction(function () use ($session): SimulationSession {
            $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status !== 'created') {
                throw new InvalidSimulationAction('Bu simulyatsiya allaqachon boshlangan.', 409);
            }

            $firstStage = ScenarioStage::query()
                ->where('scenario_version_id', $locked->scenario_version_id)
                ->orderBy('sort_order')
                ->firstOrFail();

            $locked->update([
                'status' => 'active',
                'current_stage_key' => $firstStage->key,
                'started_at' => now(),
                'last_activity_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ]);

            $roles = $locked->version()->with('roles')->firstOrFail()->roles;
            foreach ($roles as $role) {
                $isStudent = $role->key === $locked->selected_role_key;
                $locked->participants()->firstOrCreate(
                    ['role_key' => $role->key],
                    [
                        'user_id' => $isStudent ? $locked->student_id : null,
                        'actor_type' => $isStudent ? 'student' : 'ai',
                        'display_name' => $isStudent ? $locked->student->name : $role->name,
                        'status' => 'ready',
                    ],
                );
            }

            $this->events->record($locked, 'session.started', $locked->selected_role_key, $firstStage->key, [
                'scenario_version_id' => $locked->scenario_version_id,
                'selected_role_key' => $locked->selected_role_key,
            ]);

            return $locked->fresh(['participants', 'version.stages']);
        }, 3);
    }

    /**
     * @param  array{action_type: string, target_role_key?: string|null, content?: string|null, evidence_id?: int|null, idempotency_key: string, input_type?: string}  $data
     */
    public function act(SimulationSession $session, User $actor, array $data): SessionAction
    {
        return DB::transaction(function () use ($session, $actor, $data): SessionAction {
            $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->student_id !== $actor->id) {
                throw new InvalidSimulationAction('Bu simulyatsiya sizga tegishli emas.', 403);
            }
            if ($locked->status !== 'active' || $locked->current_stage_key === null) {
                throw new InvalidSimulationAction('Simulyatsiya faol holatda emas.', 409);
            }
            if ($locked->started_at && $locked->started_at->lt(now()->subMinutes(max(1, (int) config('virtual_courtroom.session.max_minutes'))))) {
                throw new InvalidSimulationAction('Mashg‘ulot vaqti tugagan. Yangi sessiya boshlang.', 409);
            }

            $existing = $locked->actions()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return $existing;
            }

            $stage = ScenarioStage::query()
                ->where('scenario_version_id', $locked->scenario_version_id)
                ->where('key', $locked->current_stage_key)
                ->firstOrFail();
            [$rule, $content, $evidence, $targetRoleKey] = $this->validateAction($stage, $locked->selected_role_key, $data);
            $action = $locked->actions()->create([
                'actor_id' => $actor->id,
                'scenario_evidence_id' => $evidence?->id,
                'actor_role_key' => $locked->selected_role_key,
                'stage_key' => $stage->key,
                'action_type' => $rule->action_type,
                'target_role_key' => $targetRoleKey,
                'content' => $content === '' ? null : $content,
                'validation_status' => 'accepted',
                'evaluation_metadata' => $rule->scoring_metadata,
                'idempotency_key' => $data['idempotency_key'],
            ]);

            if ($content !== '') {
                $locked->turns()->create([
                    'sequence' => ((int) $locked->turns()->max('sequence')) + 1,
                    'speaker_role_key' => $locked->selected_role_key,
                    'speaker_type' => 'student',
                    'input_type' => $data['input_type'] ?? 'text',
                    'content' => $content,
                    'metadata' => ['action_id' => $action->id, 'target_role_key' => $targetRoleKey],
                ]);
            }

            $this->events->record($locked, 'action.accepted', $locked->selected_role_key, $stage->key, [
                'action_id' => $action->id,
                'action_type' => $action->action_type,
                'target_role_key' => $targetRoleKey,
                'evidence_id' => $evidence?->id,
            ]);

            if ($rule->advances_to_stage_key !== null) {
                $this->transition($locked, $stage, $rule->advances_to_stage_key);
            }

            $locked->update([
                'last_activity_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ]);

            return $action->load(['session', 'evidence']);
        }, 3);
    }

    /**
     * @param  array{action_type: string, target_role_key?: string|null, content?: string|null, evidence_id?: int|null}  $data
     * @return array{next_stage: ScenarioStage|null, target_role_key: string|null}
     */
    public function rehearseAction(ScenarioStage $stage, string $roleKey, array $data): array
    {
        [$rule, , , $target] = $this->validateAction($stage, $roleKey, $data);

        return [
            'next_stage' => $rule->advances_to_stage_key === null ? null : $this->nextStage($stage, $rule->advances_to_stage_key),
            'target_role_key' => $target,
        ];
    }

    /**
     * @param  array{action_type: string, target_role_key?: string|null, content?: string|null, evidence_id?: int|null}  $data
     * @return array{ScenarioActionRule, string, ScenarioEvidence|null, string|null}
     */
    private function validateAction(ScenarioStage $stage, string $roleKey, array $data): array
    {
        $rule = $this->resolveRule($stage, $roleKey, $data['action_type']);
        $content = trim((string) ($data['content'] ?? ''));
        if ($rule->requires_content && $content === '') {
            throw new InvalidSimulationAction('Bu harakat uchun mazmun kiritilishi shart.');
        }

        $targetRoleKey = $data['target_role_key'] ?? null;
        if ($targetRoleKey !== null || $rule->response_role_keys !== null) {
            $responseRoles = $rule->responseRoles($stage->version->roles, $roleKey, $stage->key);
            $targetRoleKey ??= $responseRoles->first()?->key;
            if ($content === '' || ! $responseRoles->contains('key', $targetRoleKey)) {
                throw new InvalidSimulationAction('Bu harakat uchun tanlangan ishtirokchiga murojaat qilishga ruxsat berilmagan.');
            }
        }

        return [$rule, $content, $this->resolveEvidence($stage->scenario_version_id, $roleKey, $data['evidence_id'] ?? null, $rule), $targetRoleKey];
    }

    public function complete(SimulationSession $session): SimulationSession
    {
        return DB::transaction(function () use ($session): SimulationSession {
            $locked = SimulationSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status === 'completed') {
                return $locked;
            }
            if ($locked->status !== 'active') {
                throw new InvalidSimulationAction('Faqat faol simulyatsiyani yakunlash mumkin.', 409);
            }
            $stage = ScenarioStage::query()
                ->where('scenario_version_id', $locked->scenario_version_id)
                ->where('key', $locked->current_stage_key)
                ->firstOrFail();
            if (! $stage->is_terminal) {
                throw new InvalidSimulationAction('Avval sud jarayonining barcha bosqichlarini yakunlang.', 409);
            }

            $elapsed = $locked->started_at ? $locked->started_at->diffInSeconds(now()) : 0;
            $locked->voiceSessions()->where('status', 'active')->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);
            $locked->update([
                'status' => 'completed',
                'completed_at' => now(),
                'last_activity_at' => now(),
                'elapsed_seconds' => $elapsed,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->events->record($locked, 'session.completed', $locked->selected_role_key, $stage->key, [
                'elapsed_seconds' => $elapsed,
            ]);

            return $locked->fresh();
        }, 3);
    }

    private function resolveRule(ScenarioStage $stage, string $roleKey, string $actionType): ScenarioActionRule
    {
        $rule = $stage->actionRules()
            ->where('action_type', $actionType)
            ->whereIn('role_key', [$roleKey, '*'])
            ->orderByRaw('CASE WHEN role_key = ? THEN 0 ELSE 1 END', [$roleKey])
            ->first();

        if (! $rule) {
            throw new InvalidSimulationAction('Bu bosqichda ushbu harakatga ruxsat berilmagan.');
        }

        return $rule;
    }

    private function resolveEvidence(
        int $versionId,
        string $roleKey,
        ?int $evidenceId,
        ScenarioActionRule $rule,
    ): ?ScenarioEvidence {
        if ($rule->requires_evidence && ! $evidenceId) {
            throw new InvalidSimulationAction('Bu harakat uchun dalil tanlanishi shart.');
        }
        if (! $evidenceId) {
            return null;
        }

        $evidence = ScenarioEvidence::query()
            ->whereKey($evidenceId)
            ->where('scenario_version_id', $versionId)
            ->where('status', 'active')
            ->first();
        if (! $evidence || ! $evidence->isVisibleTo($roleKey)) {
            throw new InvalidSimulationAction('Dalil topilmadi yoki bu rol uchun ochiq emas.', 403);
        }

        return $evidence;
    }

    private function transition(SimulationSession $session, ScenarioStage $current, string $targetKey): void
    {
        $target = $this->nextStage($current, $targetKey);
        $session->update(['current_stage_key' => $target->key]);
        $this->events->record($session, 'stage.changed', $session->selected_role_key, $target->key, [
            'from' => $current->key,
            'to' => $target->key,
        ]);
    }

    private function nextStage(ScenarioStage $current, string $targetKey): ScenarioStage
    {
        if (! in_array($targetKey, $current->allowed_next_stages ?? [], true)) {
            throw new InvalidSimulationAction('Ssenariy bosqichlari ketma-ketligi buzildi.', 409);
        }
        $target = ScenarioStage::query()
            ->where('scenario_version_id', $current->scenario_version_id)
            ->where('key', $targetKey)
            ->first();
        if (! $target) {
            throw new InvalidSimulationAction('Keyingi bosqich ssenariyda topilmadi.', 409);
        }

        return $target;
    }
}
