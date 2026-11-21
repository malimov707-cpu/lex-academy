<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreGroupCourtroomRequest;
use App\Models\ClassSchedule;
use App\Models\SessionParticipant;
use App\Models\SimulationAsset;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Services\VirtualCourtroom\GroupAssessmentWorkflow;
use App\Services\VirtualCourtroom\GroupCourtroomSession;
use App\Services\VirtualCourtroom\GroupScoringEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupCourtroomController extends Controller
{
    public function __construct(private GroupCourtroomSession $classroom) {}

    public function index(Request $request): View
    {
        $isStudent = $request->user()->hasRole(UserRole::Student);
        $scenarios = SimulationScenario::query()
            ->published()
            ->with(['versions' => fn ($query) => $query->where('status', 'published')->orderByDesc('version')->with(['roles', 'stages'])])
            ->orderBy('title')
            ->get();

        return view('student.courtroom.groups.index', [
            'sessions' => SimulationSession::query()->groupVisibleTo($request->user())->with(['educationGroup', 'scenario', 'facilitator'])
                ->withCount('participants')->latest('id')->paginate(20),
            'staff' => ! $isStudent,
            'scenarios' => $scenarios,
        ]);
    }

    public function create(Request $request, GroupScoringEngine $scoring): View
    {
        $request->validate(['group_id' => ['sometimes', 'integer']]);
        $user = $request->user();
        $groups = $this->classroom->groups($user)->with('course:id,title')->orderBy('name')->get();
        $group = $request->filled('group_id') ? $groups->findOrFail($request->integer('group_id')) : $groups->first();
        $members = $group?->members()->where('role', UserRole::Student->value)->orderBy('name')->get() ?? collect();

        return view('admin.courtroom.group-setup', [
            'groups' => $groups, 'group' => $group, 'members' => $members,
            'scenarios' => $this->classroom->scenarios($user)->orderBy('title')->get(['id', 'title']),
            'schedules' => $group ? ClassSchedule::query()->where('course_id', $group->course_id)->where('group_id', $group->id)
                ->where('teacher_id', $user->id)->orderByDesc('starts_at')->get() : collect(),
            'roles' => GroupCourtroomSession::ROLES, 'defaults' => $scoring->snapshot(),
        ]);
    }

    public function store(StoreGroupCourtroomRequest $request): RedirectResponse
    {
        $session = $this->classroom->create($request->user(), $request->validated());

        return redirect()->route('courtroom.groups.show', $session);
    }

    private function viewer(Request $request, SimulationSession $session): ?SessionParticipant
    {
        abort_if($session->education_group_id === null, 404);
        if ($session->isManagedGroupBy($request->user())) {
            return null;
        }
        abort_unless($request->user()->hasRole(UserRole::Student), 403);

        return $session->participants()->where('user_id', $request->user()->id)->firstOrFail();
    }

    public function show(Request $request, SimulationSession $session): View
    {
        $member = $this->viewer($request, $session);
        $session->load(['scenario', 'educationGroup', 'version.roles', 'version.stages', 'participants', 'events']);
        $scenarioRole = $session->version->roles->firstWhere('key', $member?->metadata['scenario_role_key'] ?? '');
        $observerTasks = $session->actions()->whereIn('actor_role_key', $session->participants->where('actor_type', 'observer')->pluck('role_key'))
            ->when($member, fn ($q) => $q->where('actor_id', $member->user_id))->latest('id')->limit(50)->get();

        $avatarAssets = SimulationAsset::query()->where('enabled', true)->where('kind', 'avatar')
            ->whereIn('metadata->variant', ['male', 'female'])->get()
            ->mapWithKeys(fn (SimulationAsset $asset): array => [$asset->metadata['variant'] => asset($asset->path)]);

        $voiceMode = (config('virtual_courtroom.voice.mode') === 'browser' || ! config('virtual_courtroom.voice.server_speech_enabled'))
            ? 'browser'
            : (config('virtual_courtroom.voice.mode') === 'realtime' ? 'realtime' : 'server');
        $voiceEnabled = (bool) (config('virtual_courtroom.voice.browser_speech_enabled') || config('virtual_courtroom.voice.server_speech_enabled'));

        $sceneParties = [
            'judge' => __('Sudya'),
            'prosecutor' => __('Prokuror'),
            'victim_advocate' => __('Jabrlanuvchi advokati'),
            'victim' => __('Jabrlanuvchi'),
            'defense_advocate' => __('Himoyachi advokat'),
            'defendant' => __('Ayblanuvchi'),
            'witness_1' => __('1-Guvoh'),
            'witness_2' => __('2-Guvoh'),
            'witness_3' => __('3-Guvoh'),
        ];

        return view('student.courtroom.groups.show', [
            'session' => $session, 'member' => $member, 'roles' => GroupCourtroomSession::ROLES,
            'stage' => $session->version->stages->firstWhere('key', $session->current_stage_key),
            'actions' => $member ? $this->classroom->actionsFor($member) : [],
            'evidence' => $this->classroom->visibleEvidence($session, $member), 'scenarioRole' => $scenarioRole,
            'observerTasks' => $observerTasks,
            'turns' => $session->turns()->reorder('sequence', 'desc')->limit(60)->get()->reverse()->values(),
            'avatarAssets' => $avatarAssets,
            'voiceMode' => $voiceMode,
            'voiceEnabled' => $voiceEnabled,
            'sceneParties' => $sceneParties,
        ]);
    }

    public function feed(Request $request, SimulationSession $session): JsonResponse
    {
        $this->viewer($request, $session);
        $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'after_event' => ['nullable', 'integer', 'min:0'],
        ]);

        $activeSpeaker = $session->turns()->latest('sequence')->first()?->speaker_role_key;
        $currentStageModel = $session->version?->stages?->firstWhere('key', $session->current_stage_key);

        return response()->json([
            'status' => $session->status,
            'stage' => $session->current_stage_key,
            'stage_name' => $currentStageModel?->name ?? $session->current_stage_key,
            'board_evidence_id' => data_get($session->settings, 'board_evidence_id'),
            'active_speaker' => $activeSpeaker,
            'turns' => $session->turns()->where('sequence', '>', $request->integer('after'))->limit(100)
                ->get(['sequence', 'speaker_role_key', 'speaker_type', 'content', 'created_at']),
            'events' => $session->events()->where('sequence', '>', $request->integer('after_event', 0))->limit(50)
                ->get(['sequence', 'type', 'actor_role_key', 'stage_key', 'payload', 'occurred_at']),
            'participants' => $session->participants()->get(['role_key', 'display_name', 'actor_type', 'status']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function attend(Request $request, SimulationSession $session): RedirectResponse|JsonResponse
    {
        $this->classroom->attend($session, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['status' => 'present']);
        }

        return redirect()->route('courtroom.groups.show', $session);
    }

    public function act(Request $request, SimulationSession $session): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'stage_key' => ['required', 'string', 'max:80'], 'action_type' => ['required', 'string', 'max:80'],
            'idempotency_key' => ['required', 'uuid'], 'content' => ['nullable', 'string', 'max:8000'],
            'evidence_id' => ['nullable', 'integer'], 'target_role_key' => ['nullable', 'string', 'max:60'],
            'question_id' => ['nullable', 'integer', 'min:0'], 'answer' => ['nullable', 'string', 'max:1'],
        ]);
        $this->classroom->act($session, $request->user(), $data);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => __('Qayd saqlandi.'),
            ]);
        }

        return redirect()->route('courtroom.groups.show', $session)->with('status', __('Qayd saqlandi.'));
    }

    public function start(Request $request, SimulationSession $session): RedirectResponse|JsonResponse
    {
        $this->classroom->transition($session, $request->user(), 'start');

        if ($request->wantsJson()) {
            return response()->json(['status' => 'active']);
        }

        return redirect()->route('courtroom.groups.show', $session);
    }

    public function pause(Request $request, SimulationSession $session): RedirectResponse|JsonResponse
    {
        $this->classroom->transition($session, $request->user(), 'pause');

        if ($request->wantsJson()) {
            return response()->json(['status' => 'paused', 'message' => __('Mashg‘ulot to‘xtatildi (pauza).')]);
        }

        return redirect()->route('courtroom.groups.show', $session)->with('status', __('Mashg‘ulot to‘xtatildi (pauza).'));
    }

    public function resume(Request $request, SimulationSession $session): RedirectResponse|JsonResponse
    {
        $this->classroom->transition($session, $request->user(), 'resume');

        if ($request->wantsJson()) {
            return response()->json(['status' => 'active', 'message' => __('Mashg‘ulot davom ettirilmoqda.')]);
        }

        return redirect()->route('courtroom.groups.show', $session)->with('status', __('Mashg‘ulot davom ettirilmoqda.'));
    }

    public function evidence(Request $request, SimulationSession $session): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['evidence_id' => ['required', 'integer']]);
        $this->classroom->presentEvidence($session, $request->user(), $data['evidence_id']);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'board_evidence_id' => $data['evidence_id'], 'message' => __('Dalil doskaga chiqarildi.')]);
        }

        return redirect()->route('courtroom.groups.show', $session)->with('status', __('Dalil doskaga chiqarildi.'));
    }

    public function stage(Request $request, SimulationSession $session): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'string', 'max:80'], 'to' => ['required', 'string', 'max:80']]);
        $this->classroom->transition($session, $request->user(), 'stage', $data['from'], $data['to']);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'stage' => $data['to']]);
        }

        return redirect()->route('courtroom.groups.show', $session);
    }

    public function complete(Request $request, SimulationSession $session, GroupAssessmentWorkflow $workflow): RedirectResponse
    {
        $completed = $this->classroom->transition($session, $request->user(), 'complete');
        $workflow->prepare($completed);

        return redirect()->route('courtroom.results.show', $session);
    }
}
