<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\InvalidSimulationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSessionActionRequest;
use App\Http\Requests\StoreSimulationSessionRequest;
use App\Models\ScenarioActionRule;
use App\Models\ScenarioEvidence;
use App\Models\SimulationAsset;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Services\Ai\AiUnavailableException;
use App\Services\VirtualCourtroom\CourtroomAiOrchestrator;
use App\Services\VirtualCourtroom\CourtroomLearningFeedback;
use App\Services\VirtualCourtroom\SimulationEvaluator;
use App\Services\VirtualCourtroom\SimulationStateMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourtroomSessionController extends Controller
{
    public function store(StoreSimulationSessionRequest $request): RedirectResponse
    {
        Gate::authorize('create', SimulationSession::class);
        $scenario = SimulationScenario::query()->findOrFail($request->integer('scenario_id'));
        Gate::authorize('view', $scenario);
        abort_unless($scenario->status === 'published', 404);
        abort_if($scenario->legal_area === 'criminal', 404);
        $version = $scenario->versions()->where('status', 'published')->orderByDesc('version')->firstOrFail();
        $role = $version->roles()->where('key', $request->string('role_key'))->where('is_student_selectable', true)->first();
        abort_unless($role, 422, 'Tanlangan rol ushbu ssenariy uchun mavjud emas.');

        $session = SimulationSession::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $version->id,
            'student_id' => $request->user()->id,
            'selected_role_key' => $role->key,
            'status' => 'created',
            'settings' => [
                'captions' => $request->boolean('captions', true),
                'auto_camera' => $request->boolean('auto_camera', true),
            ],
        ]);

        return redirect()->route('student.courtroom.sessions.show', $session)
            ->with('status', __('Rol tanlandi. Maxfiy rol yo‘riqnomasini o‘qib, simulyatsiyani boshlang.'));
    }

    public function show(SimulationSession $session): View
    {
        Gate::authorize('view', $session);
        $session->load([
            'scenario', 'version.roles', 'version.stages.actionRules', 'version.verifiedLegalReferences', 'participants',
            'turns', 'events', 'evaluation.criterionResults.criterion',
        ]);
        $evidence = ScenarioEvidence::query()
            ->where('scenario_version_id', $session->scenario_version_id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ScenarioEvidence $item): bool => $item->isVisibleTo($session->selected_role_key))
            ->values();
        $currentStage = $session->version->stages->firstWhere('key', $session->current_stage_key);
        $allowedActions = $currentStage
            ? $currentStage->actionRules
                ->filter(fn (ScenarioActionRule $rule): bool => in_array($rule->role_key, ['*', $session->selected_role_key], true))
                ->sortBy(fn (ScenarioActionRule $rule): int => $rule->action_type === 'advance_stage' ? 10 : 0)
                ->unique('action_type')->values()
            : collect();
        $responseOptions = $allowedActions->mapWithKeys(fn (ScenarioActionRule $rule): array => [
            $rule->id => $rule->responseRoles($session->version->roles, $session->selected_role_key, $session->current_stage_key ?? ''),
        ]);
        $sceneParties = [];
        if ($session->version->roles->contains('key', 'defense_attorney') && ! $session->version->roles->contains('key', 'defendant')) {
            $sceneParties['defendant'] = $session->scenario->legal_area === 'criminal' ? __('Ayblanuvchi') : __('Javobgar');
        }
        if ($session->scenario->legal_area === 'civil' && $session->version->roles->contains('key', 'prosecutor') && ! $session->version->roles->contains('key', 'claimant')) {
            $sceneParties['claimant'] = __('Da’vogar');
        }

        $activeVoiceSession = null;
        if ($session->status === 'active' && config('virtual_courtroom.voice.server_speech_enabled')) {
            $studentId = auth()->id() ?? $session->student_id;
            $activeVoiceSession = $session->voiceSessions()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if (! $activeVoiceSession && $studentId) {
                $activeVoiceSession = $session->voiceSessions()->create([
                    'student_id' => $studentId,
                    'provider' => 'openai',
                    'status' => 'active',
                    'transcript_consent' => true,
                    'audio_recording' => false,
                    'consented_at' => now(),
                    'metadata' => ['capabilities' => ['speech_recognition' => true, 'speech_synthesis' => true]],
                ]);
            }
        }

        return view('student.courtroom.session', [
            'session' => $session,
            'selectedRole' => $session->version->roles->firstWhere('key', $session->selected_role_key),
            'currentStage' => $currentStage,
            'allowedActions' => $allowedActions,
            'responseOptions' => $responseOptions,
            'sceneParties' => $sceneParties,
            'evidence' => $evidence,
            'avatarAssets' => $session->status === 'active'
                ? SimulationAsset::query()->where('enabled', true)->where('kind', 'avatar')
                    ->whereIn('metadata->variant', ['male', 'female'])->get()
                    ->mapWithKeys(fn (SimulationAsset $asset): array => [$asset->metadata['variant'] => asset($asset->path)])
                : collect(),
            'activeVoiceSession' => $activeVoiceSession,
            'voiceMode' => (config('virtual_courtroom.voice.mode') === 'browser' || ! config('virtual_courtroom.voice.server_speech_enabled'))
                ? 'browser'
                : (config('virtual_courtroom.voice.mode') === 'realtime' ? 'realtime' : 'server'),
            'voiceEnabled' => (bool) (config('virtual_courtroom.voice.browser_speech_enabled') || config('virtual_courtroom.voice.server_speech_enabled')),
        ]);
    }

    public function start(SimulationSession $session, SimulationStateMachine $stateMachine): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $session);

        try {
            $started = $stateMachine->start($session);
        } catch (InvalidSimulationAction $exception) {
            return request()->expectsJson()
                ? response()->json(['error' => $exception->getMessage()], $exception->status)
                : back()->withErrors(['session' => $exception->getMessage()]);
        }

        return request()->expectsJson()
            ? response()->json(['session' => $this->sessionState($started)])
            : redirect()->route('student.courtroom.sessions.show', $started)->with('courtroom_entrance', $started->id);
    }

    public function action(
        StoreSessionActionRequest $request,
        SimulationSession $session,
        SimulationStateMachine $stateMachine,
        CourtroomAiOrchestrator $orchestrator,
    ): JsonResponse {
        Gate::authorize('update', $session);

        try {
            $action = $stateMachine->act($session, $request->user(), $request->validated());
        } catch (InvalidSimulationAction $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status);
        }

        $ai = null;
        $aiError = null;
        if (! $action->wasRecentlyCreated && $action->ai_response !== null) {
            $turn = $session->turns()->where('speaker_type', 'ai')->where('metadata->action_id', $action->id)->first();
            if ($turn) {
                $ai = [
                    'turn_id' => $turn->id, 'role_key' => $turn->speaker_role_key,
                    'role_name' => $session->version->roles()->where('key', $turn->speaker_role_key)->value('name'),
                    'content' => $turn->content, 'confidence' => $turn->metadata['confidence'] ?? null,
                ];
            }
        }
        if ($action->wasRecentlyCreated && $action->content !== null && $action->action_type !== 'advance_stage') {
            try {
                $ai = $orchestrator->respond($action);
            } catch (AiUnavailableException|ConnectionException $exception) {
                $aiError = $exception instanceof AiUnavailableException
                    ? $exception->getMessage()
                    : 'AI servisiga ulanish imkoni bo‘lmadi.';
            }
        }
        $fresh = $session->fresh();

        return response()->json([
            'action' => [
                'id' => $action->id,
                'type' => $action->action_type,
                'target_role_key' => $action->target_role_key,
                'stage' => $action->stage_key,
                'status' => $action->validation_status,
            ],
            'session' => $this->sessionState($fresh),
            'ai' => $ai,
            'ai_error' => $aiError,
        ], $aiError ? 202 : 200);
    }

    public function complete(
        SimulationSession $session,
        SimulationStateMachine $stateMachine,
        SimulationEvaluator $evaluator,
    ): RedirectResponse|JsonResponse {
        Gate::authorize('update', $session);

        try {
            $completed = $stateMachine->complete($session);
            $evaluation = $evaluator->generate($completed);
        } catch (InvalidSimulationAction $exception) {
            return request()->expectsJson()
                ? response()->json(['error' => $exception->getMessage()], $exception->status)
                : back()->withErrors(['session' => $exception->getMessage()]);
        }

        return request()->expectsJson()
            ? response()->json(['report_url' => route('student.courtroom.sessions.report', $completed), 'score' => $evaluation->overall_score])
            : redirect()->route('student.courtroom.sessions.report', $completed);
    }

    public function timeline(SimulationSession $session): JsonResponse
    {
        Gate::authorize('view', $session);

        return response()->json(['events' => $session->events()->get()->map(fn ($event): array => [
            'id' => $event->id,
            'sequence' => $event->sequence,
            'type' => $event->type,
            'stage' => $event->stage_key,
            'actor_role' => $event->actor_role_key,
            'payload' => $event->payload,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ])]);
    }

    public function report(SimulationSession $session): View
    {
        Gate::authorize('view', $session);
        abort_unless($session->status === 'completed', 409);
        $session->load([
            'scenario', 'version.roles', 'actions.evidence', 'events',
            'evaluation.criterionResults.criterion', 'evaluation.reviews.reviewer',
        ]);
        abort_unless($session->evaluation, 404);
        $personalNotes = Gate::allows('update', $session) ? $session->evidenceAnnotations()
            ->whereHas('evidence', fn ($query) => $query->where('scenario_version_id', $session->scenario_version_id)->where('status', 'active'))
            ->with('evidence')->orderByDesc('id')->cursorPaginate(20, ['*'], 'notes_cursor') : null;

        return view('student.courtroom.report', [
            'session' => $session,
            'evaluation' => $session->evaluation,
            'personalNotes' => $personalNotes,
            'previousAttempt' => $this->previousComparableAttempt($session),
            'canRequestFeedback' => filled(config('ai_tutor.api_key')) && filled(config('ai_tutor.base_url'))
                && $session->actions->contains(fn ($action): bool => $action->validation_status === 'accepted' && filled($action->content)),
        ]);
    }

    private function previousComparableAttempt(SimulationSession $session): ?SimulationSession
    {
        if ($session->completed_at === null) {
            return null;
        }

        return SimulationSession::query()
            ->where('student_id', $session->student_id)
            ->where('scenario_version_id', $session->scenario_version_id)
            ->where('selected_role_key', $session->selected_role_key)
            ->where('settings->scoring_version', $session->settings['scoring_version'] ?? null)
            ->where('status', 'completed')
            ->has('evaluation')
            ->where(function (Builder $earlier) use ($session): void {
                $earlier->where('completed_at', '<', $session->completed_at)
                    ->orWhere(function (Builder $sameSecond) use ($session): void {
                        $sameSecond->where('completed_at', $session->completed_at)->where('id', '<', $session->id);
                    });
            })
            ->with('evaluation.criterionResults')
            ->orderByDesc('completed_at')->orderByDesc('id')->first();
    }

    public function feedback(SimulationSession $session, CourtroomLearningFeedback $feedback): RedirectResponse
    {
        Gate::authorize('update', $session);
        abort_unless($session->status === 'completed', 409);
        $evaluation = $session->evaluation()->firstOrFail();
        $redirect = redirect()->route('student.courtroom.sessions.report', $session);

        try {
            $feedback->generate($evaluation);
        } catch (AiUnavailableException $exception) {
            return $redirect->withErrors(['ai_feedback' => $exception->getMessage()]);
        }

        return $redirect->with('status', __('AI tavsiyasi hisobotga qo‘shildi.'));
    }

    /** @return array<string, mixed> */
    private function sessionState(SimulationSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'current_stage' => $session->current_stage_key,
            'lock_version' => $session->lock_version,
        ];
    }
}
